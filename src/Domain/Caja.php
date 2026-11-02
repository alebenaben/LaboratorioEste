<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\App;
use App\Core\Config;
use App\Core\Falta;
use App\Core\Historial;
use App\Core\HttpError;

/**
 * Caja y cobranza (CU-26 a CU-32): facturación mensual, cobros, débito automático,
 * cuenta corriente y envío de comprobantes.
 */
final class Caja
{
    public static function nroFactura(array $f): string
    {
        return sprintf('%05d-%08d', (int)$f['pv'], (int)$f['numero']);
    }

    public static function nroRecibo(array $r): string
    {
        return sprintf('R%05d-%08d', (int)$r['pv'], (int)$r['numero']);
    }

    public static function factura(int $id): array
    {
        $f = App::$db->one('SELECT * FROM facturas WHERE id = :id', ['id' => $id]) ?? throw new HttpError(404, 'No existe esa factura.');
        return self::hidratarFactura($f);
    }

    public static function hidratarFactura(array $f): array
    {
        $f['id'] = (int)$f['id'];
        $f['grupo_id'] = (int)$f['grupo_id'];
        $f['importe'] = (float)$f['importe'];
        $f['base'] = (float)$f['base'];
        $f['alicuota'] = $f['alicuota'] === null ? null : (float)$f['alicuota'];
        $f['receptor'] = is_array($f['receptor']) ? $f['receptor'] : (json_decode((string)$f['receptor'], true) ?: []);
        $f['lineas'] = is_array($f['lineas']) ? $f['lineas'] : (json_decode((string)$f['lineas'], true) ?: []);
        $f['nro'] = self::nroFactura($f);
        return $f;
    }

    public static function recibo(int $id): array
    {
        $r = App::$db->one('SELECT * FROM recibos WHERE id = :id', ['id' => $id]) ?? throw new HttpError(404, 'No existe ese recibo.');
        $r['nro'] = self::nroRecibo($r);
        $r['importe'] = (float)$r['importe'];
        return $r;
    }

    public static function letra(array $g): string
    {
        return ($g['fiscal_cond'] ?? 'CF') === 'RI' ? 'A' : 'B';
    }

    /** Datos del receptor al momento de facturar. */
    public static function receptor(array $g, array $t): array
    {
        if (($g['fiscal_cond'] ?? 'CF') === 'CF') {
            return ['cond' => 'CF', 'nombre' => $t['nombre'], 'doc' => 'DNI ' . $t['doc']];
        }
        return ['cond' => $g['fiscal_cond'], 'nombre' => $g['fiscal_razon'] ?: $t['nombre'], 'doc' => 'CUIT ' . ($g['fiscal_cuit'] ?: '—')];
    }

    /** Próximo número de factura que se emitiría (para Configuración → Puntos de venta). */
    public static function proximaFactura(string $letra = 'B'): string
    {
        $pv = Config::pvCuotas();
        $ult = (int)(Config::get('ultimos_numeros', [])[$letra] ?? 0);
        $n = (int)(App::$db->val('SELECT ultimo FROM numeradores WHERE clave = :c', ['c' => "F$letra-$pv"]) ?? 0);
        return sprintf('%05d-%08d', $pv, max($n, $ult) + 1);
    }

    /** P-15: se puede facturar desde el inicio del sistema hasta el mes siguiente al actual. */
    public static function periodoValido(string $per, ?string $hoy = null): void
    {
        $hoy ??= Fechas::hoy();
        if (!preg_match('/^\d{4}-\d{2}$/', $per)) {
            throw new Falta('Elegí el mes.');
        }
        $ini = Config::inicio()['periodo'];
        if ($per < $ini) {
            throw new Falta('El sistema factura desde ' . Fmt::perLabel($ini) . '.');
        }
        if ($per > Fechas::perMas(substr($hoy, 0, 7), 1)) {
            throw new Falta('Solo se puede facturar hasta el mes que viene.');
        }
    }

    /** Grupos que faltan facturar en el período, con su importe. */
    public static function aFacturar(string $per): array
    {
        if ($per < Config::inicio()['periodo']) {
            return [];
        }
        $r = [];
        foreach (App::$db->col('SELECT g.id FROM grupos g WHERE NOT EXISTS (SELECT 1 FROM facturas f WHERE f.grupo_id = g.id AND f.periodo = :p) ORDER BY g.id', ['p' => $per]) as $gid) {
            $g = Padron::grupo((int)$gid);
            $imp = Padron::cuotaGrupo($g, $per);
            if ($imp > 0) {
                $r[] = ['g' => $g, 'importe' => $imp];
            }
        }
        return $r;
    }

    /**
     * CU-26: emite una factura por grupo para el período. Es atómica (S-02): si falla una, no se emite ninguna.
     * Las facturas quedan "pendientes de CAE" y el worker las autoriza en ARCA (CU-27).
     */
    public static function facturar(string $per, string $usuario): array
    {
        self::periodoValido($per);
        $db = App::$db;
        return $db->tx(function () use ($db, $per, $usuario) {
            $db->exec('SELECT pg_advisory_xact_lock(515151)');
            $hoy = Fechas::hoy();
            $emision = $hoy > "$per-01" ? $hoy : "$per-01";
            $vto = sprintf('%s-%02d', $per, Config::diaVto());
            $pv = Config::pvCuotas();
            $ult = Config::get('ultimos_numeros', []);
            $emitidas = [];
            foreach (self::aFacturar($per) as ['g' => $g, 'importe' => $imp]) {
                $t = Padron::titular($g['id']);
                $letra = self::letra($g);
                $promo = Padron::promoVigente($g, $per);
                $num = Usos::siguiente("F$letra-$pv", (int)($ult[$letra] ?? 0));
                $id = (int)$db->insert('facturas', [
                    'letra' => $letra, 'pv' => $pv, 'numero' => $num, 'grupo_id' => $g['id'], 'plan_id' => $g['plan_id'],
                    'periodo' => $per, 'emision' => $emision, 'vto' => $vto, 'importe' => $imp,
                    'base' => Padron::cuotaBase($g['id'], $per),
                    'promo_pct' => $promo['pct'] ?? 0, 'promo_mes' => $promo ? Fechas::perDif($promo['desde'], $per) + 1 : 0,
                    'promo_meses' => $promo['meses'] ?? 0, 'promo_hasta' => $promo['hasta'] ?? null,
                    'alicuota' => Catalogo::plan($g['plan_id'])['alicuota'],
                    'receptor' => json_encode(self::receptor($g, $t), JSON_UNESCAPED_UNICODE),
                    'lineas' => json_encode(array_map(fn($a) => ['nombre' => $a['nombre'], 'nro' => $a['nro'], 'cat' => $a['categoria_id'], 'cuota' => $a['cuota']], Padron::activosEn($g['id'], $per)), JSON_UNESCAPED_UNICODE),
                    'usuario' => $usuario,
                ]);
                $emitidas[] = ['id' => $id, 'importe' => $imp];
            }
            if ($emitidas) {
                $db->insert('cola_tareas', ['tipo' => 'cae', 'payload' => json_encode(['periodo' => $per])]);
                $tot = array_sum(array_column($emitidas, 'importe'));
                Config::set('ultima_emision', ['periodo' => $per, 'cant' => count($emitidas), 'total' => $tot, 'fecha' => $hoy], $usuario);
                Historial::log($db, 'Facturación', count($emitidas) . ' facturas de ' . Fmt::perLabel($per) . ' por ' . Fmt::money($tot));
            }
            return $emitidas;
        });
    }

    /**
     * CU-29 / CU-30: cobra una factura entera y emite el recibo (P-06: sin pagos parciales).
     * Cobrar la misma factura dos veces genera un solo recibo.
     */
    public static function cobrar(int $facturaId, string $medio, string $fecha, bool $masivo, string $usuario): array
    {
        if (!in_array($medio, Catalogo::MEDIOS, true)) {
            throw new Falta('Elegí el medio de pago.');
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha)) {
            throw new Falta('La fecha del pago no es válida.');
        }
        $db = App::$db;
        $r = $db->tx(function () use ($db, $facturaId, $medio, $fecha, $masivo, $usuario) {
            $f = $db->one('SELECT * FROM facturas WHERE id = :id FOR UPDATE', ['id' => $facturaId]) ?? throw new HttpError(404);
            if ($f['estado'] === 'pagada') {
                $rec = $db->one('SELECT * FROM recibos WHERE factura_id = :f', ['f' => $facturaId]);
                throw new Falta('Esa factura ya está pagada' . ($rec ? ' (recibo ' . self::nroRecibo($rec) . ')' : '') . '.');
            }
            $pv = Config::pvCuotas();
            $n = Usos::siguiente("R-$pv");
            $id = (int)$db->insert('recibos', [
                'pv' => $pv, 'numero' => $n, 'factura_id' => $facturaId, 'grupo_id' => $f['grupo_id'], 'fecha' => $fecha,
                'importe' => $f['importe'], 'medio' => $medio, 'masivo' => $masivo, 'usuario' => $usuario,
            ]);
            $db->exec("UPDATE facturas SET estado = 'pagada' WHERE id = :id", ['id' => $facturaId]);
            $rec = self::recibo($id);
            $t = Padron::titular((int)$f['grupo_id']);
            Historial::log($db, 'Cobro', "Recibo {$rec['nro']} · {$t['nombre']} · " . Fmt::money($f['importe']) . " · $medio");
            return $rec;
        });
        // Envío automático del recibo por mail (CU-48).
        $g = Padron::grupo((int)$r['grupo_id']);
        if (Mails::reciboAutomatico() && $g['email']) {
            self::encolarMail('recibo', $r['id']);
        }
        return $r;
    }

    /** CU-29: facturas pendientes de los grupos con débito automático. */
    public static function pendientesDebito(): array
    {
        return array_map([self::class, 'hidratarFactura'], App::$db->all("SELECT f.* FROM facturas f JOIN grupos g ON g.id = f.grupo_id WHERE f.estado = 'pendiente' AND g.forma_pago = 'Débito automático' ORDER BY f.vto, f.id"));
    }

    public static function pendientes(): array
    {
        return array_map([self::class, 'hidratarFactura'], App::$db->all("SELECT * FROM facturas WHERE estado = 'pendiente' ORDER BY vto, id"));
    }

    /** CU-32: movimientos de la cuenta corriente con saldo acumulado. */
    public static function cuentaCorriente(int $grupoId): array
    {
        $ini = Config::inicio();
        $mov = [['fecha' => $ini['fecha'], 'orden' => 0, 'tipo' => 'Saldo inicial', 'nro' => '', 'det' => 'Inicio del sistema: al día', 'debe' => 0.0, 'haber' => 0.0]];
        foreach (App::$db->all('SELECT * FROM facturas WHERE grupo_id = :g', ['g' => $grupoId]) as $f) {
            $f = self::hidratarFactura($f);
            $mov[] = ['fecha' => $f['emision'], 'orden' => 1, 'tipo' => 'Factura ' . $f['letra'], 'nro' => $f['nro'], 'fid' => $f['id'], 'det' => 'Cuota ' . Fmt::perLabel($f['periodo']) . ($f['promo_pct'] ? " (promoción −{$f['promo_pct']}%)" : ''), 'debe' => $f['importe'], 'haber' => 0.0];
        }
        foreach (App::$db->all('SELECT * FROM recibos WHERE grupo_id = :g', ['g' => $grupoId]) as $r) {
            $mov[] = ['fecha' => $r['fecha'], 'orden' => 2, 'tipo' => 'Recibo', 'nro' => self::nroRecibo($r), 'rid' => (int)$r['id'], 'det' => $r['medio'], 'debe' => 0.0, 'haber' => (float)$r['importe']];
        }
        usort($mov, fn($a, $b) => [$a['fecha'], $a['orden']] <=> [$b['fecha'], $b['orden']]);
        $s = 0.0;
        foreach ($mov as &$m) {
            $s = round($s + $m['debe'] - $m['haber'], 2);
            $m['saldo'] = $s;
        }
        return $mov;
    }

    // ------------------------------------------------------------------ mails (CU-28, CU-31)

    /** Encola el mail de una factura o un recibo al email del grupo. */
    public static function encolarMail(string $tipo, int $id): void
    {
        if ($tipo === 'factura') {
            $f = self::factura($id);
            $g = Padron::grupo($f['grupo_id']);
            if (!$g['email']) {
                throw new Falta('El grupo no tiene email: cargalo en la ficha del titular.');
            }
            if ($f['cae_estado'] !== 'autorizada') {
                throw new Falta('La factura todavía no tiene CAE: se puede enviar cuando ARCA la autorice.');
            }
            $m = Mails::armar('factura', Mails::varsFactura($f));
            Mails::encolar($g['email'], $m['asunto'], $m['cuerpo'], ['tipo' => 'factura', 'id' => $id], ['tabla' => 'facturas', 'id' => $id]);
            App::$db->exec("UPDATE facturas SET mail_estado = 'encolado', mail_fecha = now() WHERE id = :id", ['id' => $id]);
        } else {
            $r = self::recibo($id);
            $g = Padron::grupo((int)$r['grupo_id']);
            if (!$g['email']) {
                throw new Falta('El grupo no tiene email: cargalo en la ficha del titular.');
            }
            $m = Mails::armar('recibo', Mails::varsRecibo($r));
            Mails::encolar($g['email'], $m['asunto'], $m['cuerpo'], ['tipo' => 'recibo', 'id' => $id], ['tabla' => 'recibos', 'id' => $id]);
            App::$db->exec("UPDATE recibos SET mail_estado = 'encolado' WHERE id = :id", ['id' => $id]);
        }
    }

    /** CU-28: estado del envío masivo de un período. */
    public static function estadoMails(string $per): array
    {
        $fs = App::$db->all('SELECT f.id, f.cae_estado, f.mail_estado, g.email, g.id AS gid FROM facturas f JOIN grupos g ON g.id = f.grupo_id WHERE f.periodo = :p ORDER BY f.numero', ['p' => $per]);
        $con = array_filter($fs, fn($f) => $f['email'] !== '');
        $sin = array_filter($fs, fn($f) => $f['email'] === '');
        $env = array_filter($fs, fn($f) => $f['mail_estado'] !== null);
        $aEnviar = array_values(array_filter($con, fn($f) => $f['mail_estado'] === null && $f['cae_estado'] === 'autorizada'));
        $sinCae = array_filter($con, fn($f) => $f['mail_estado'] === null && $f['cae_estado'] !== 'autorizada');
        return ['total' => count($fs), 'con' => count($con), 'sin' => array_values($sin), 'enviadas' => count($env), 'aEnviar' => $aEnviar, 'sinCae' => count($sinCae)];
    }

    /** CU-28: encola un mail por factura del período (no reenvía las ya enviadas). */
    public static function enviarMailsPeriodo(string $per): int
    {
        $n = 0;
        foreach (self::estadoMails($per)['aEnviar'] as $f) {
            self::encolarMail('factura', (int)$f['id']);
            $n++;
        }
        if ($n) {
            Historial::log(App::$db, 'Mails', "Facturas de " . Fmt::perLabel($per) . " enviadas por mail a $n");
        }
        return $n;
    }
}
