<?php
declare(strict_types=1);

namespace App\Service;

use App\Core\App;
use App\Core\Env;
use App\Core\Historial;
use App\Domain\Caja;
use App\Domain\Documentos;
use App\Domain\Fmt;
use App\Domain\Padron;
use App\Domain\Promociones;
use App\Service\Arca\Arca;
use App\Service\Arca\ArcaNoResponde;
use App\Service\Arca\ArcaRechazo;
use App\Service\Arca\ArcaWebService;
use App\Service\Arca\Facturador;

/**
 * Worker: procesa la cola de tareas guardada en PostgreSQL (ADR-06).
 *  - cae:  pide el CAE de las facturas pendientes, en orden de número (CU-27).
 *  - mail: envía un mail con el PDF adjunto (CU-19, CU-28, CU-30, CU-36).
 * Reintenta con espera creciente y avisa a los administradores lo que falla.
 */
final class Worker
{
    private bool $seguir = true;
    private ?Facturador $arca = null;

    public function detener(): void
    {
        $this->seguir = false;
    }

    public function facturador(): Facturador
    {
        return $this->arca ??= Arca::crear();
    }

    public function usarFacturador(Facturador $f): void
    {
        $this->arca = $f;
    }

    /** Loop principal. Termina la tarea en curso al recibir SIGTERM. */
    public function correr(): void
    {
        if (function_exists('pcntl_async_signals')) {
            pcntl_async_signals(true);
            pcntl_signal(SIGTERM, fn() => $this->detener());
            pcntl_signal(SIGINT, fn() => $this->detener());
        }
        $ultimoAviso = 0;
        $ultimaDiaria = '';
        fwrite(STDOUT, json_encode(['nivel' => 'info', 'msg' => 'worker iniciado', 'arca' => Arca::modo(), 'mail' => Mailer::modo()]) . "\n");
        while ($this->seguir) {
            try {
                $n = $this->procesar(20);
                if (time() - $ultimoAviso >= 300) {
                    $this->heartbeat(Env::get('HEARTBEAT_WORKER_URL'));
                    $ultimoAviso = time();
                }
                if ($ultimaDiaria !== date('Y-m-d')) {
                    $this->tareaDiaria();
                    $ultimaDiaria = date('Y-m-d');
                }
            } catch (\Throwable $e) {
                fwrite(STDERR, json_encode(['nivel' => 'error', 'msg' => $e->getMessage()]) . "\n");
                $n = 0;
                sleep(5);
                try {
                    App::$db = \App\Core\Db::conectar();
                } catch (\Throwable) {
                }
            }
            if (!$n) {
                for ($i = 0; $i < 10 && $this->seguir; $i++) {
                    sleep(1);
                }
            }
        }
        fwrite(STDOUT, json_encode(['nivel' => 'info', 'msg' => 'worker detenido']) . "\n");
    }

    /** Procesa hasta $max tareas listas. Devuelve cuántas procesó. */
    public function procesar(int $max = 50): int
    {
        $n = 0;
        while ($n < $max && $this->seguir) {
            $t = App::$db->one(
                "UPDATE cola_tareas SET estado = 'en_curso', intentos = intentos + 1, actualizado = now()
                 WHERE id = (SELECT id FROM cola_tareas WHERE estado = 'pendiente' AND ejecutar_desde <= now()
                             ORDER BY id FOR UPDATE SKIP LOCKED LIMIT 1)
                 RETURNING *"
            );
            if (!$t) {
                break;
            }
            $n++;
            $p = json_decode((string)$t['payload'], true) ?: [];
            try {
                match ($t['tipo']) {
                    'cae' => $this->cae(),
                    'mail' => $this->mail($p),
                    default => throw new \RuntimeException("Tarea desconocida: {$t['tipo']}"),
                };
                App::$db->exec("UPDATE cola_tareas SET estado = 'hecha', error = NULL, actualizado = now() WHERE id = :id", ['id' => $t['id']]);
            } catch (MailRebote $e) {
                $this->fallida($t, $e->getMessage());
                $this->marcarRebote($p, $e->getMessage());
            } catch (ArcaRechazo $e) {
                // Error de configuración o rechazo: queda registrado y se avisa a los administradores.
                App::$db->exec("UPDATE cola_tareas SET estado = 'fallida', error = :e, actualizado = now() WHERE id = :id", ['e' => $e->getMessage(), 'id' => $t['id']]);
                $this->aviso('bad', 'ARCA: ' . $e->getMessage());
            } catch (\Throwable $e) {
                $intentos = (int)$t['intentos'];
                if ($intentos >= (int)$t['max_intentos']) {
                    $this->fallida($t, $e->getMessage());
                    $this->aviso('bad', ($t['tipo'] === 'cae' ? 'No se pudo obtener el CAE de ARCA' : 'No se pudo enviar un mail') . ' después de varios intentos: ' . $e->getMessage());
                } else {
                    $espera = min(3600, 30 * (2 ** ($intentos - 1)));
                    App::$db->exec("UPDATE cola_tareas SET estado = 'pendiente', error = :e, ejecutar_desde = now() + (:s || ' seconds')::interval, actualizado = now() WHERE id = :id", ['e' => $e->getMessage(), 's' => (string)$espera, 'id' => $t['id']]);
                }
            }
        }
        return $n;
    }

    private function fallida(array $t, string $err): void
    {
        App::$db->exec("UPDATE cola_tareas SET estado = 'fallida', error = :e, actualizado = now() WHERE id = :id", ['e' => $err, 'id' => $t['id']]);
    }

    public function aviso(string $nivel, string $texto): void
    {
        $ya = App::$db->val("SELECT 1 FROM avisos WHERE texto = :t AND leido IS NULL", ['t' => $texto]);
        if (!$ya) {
            App::$db->insert('avisos', ['nivel' => $nivel, 'texto' => $texto]);
        }
    }

    // ------------------------------------------------------------------ CU-27 CAE

    /** Pide el CAE de todas las facturas pendientes, en orden de número, por letra. */
    public function cae(): void
    {
        $arca = $this->facturador();
        $fs = App::$db->all("SELECT * FROM facturas WHERE cae_estado = 'pendiente' ORDER BY letra, pv, numero");
        $porTipo = [];
        foreach ($fs as $f) {
            $porTipo[$f['letra'] . '-' . $f['pv']][] = Caja::hidratarFactura($f);
        }
        foreach ($porTipo as $lista) {
            $f0 = $lista[0];
            $tipo = Arca::TIPO[$f0['letra']];
            $ult = $arca->ultimoAutorizado((int)$f0['pv'], $tipo);
            foreach ($lista as $f) {
                $num = (int)$f['numero'];
                if ($num <= $ult) {
                    // Ya está en ARCA (por ejemplo, después de restaurar una copia): se trae el CAE.
                    $c = $arca->consultar((int)$f['pv'], $tipo, $num);
                    if ($c) {
                        $this->guardarCae($f, $c);
                        continue;
                    }
                    $this->rechazar($f, "El número {$f['nro']} ya fue usado en ARCA y no se encontró el comprobante.");
                    continue;
                }
                if ($num !== $ult + 1) {
                    $this->aviso('bad', "La numeración local de facturas {$f['letra']} ({$f['nro']}) no coincide con ARCA (último autorizado: $ult). No se pide el CAE hasta corregirlo en Configuración → Puntos de venta.");
                    break;
                }
                try {
                    $c = $arca->autorizar(Arca::desdeFactura($f));
                    $this->guardarCae($f, $c);
                    $ult = $num;
                } catch (ArcaRechazo $e) {
                    $this->rechazar($f, $e->getMessage());
                    break; // sin CAE para este número, los siguientes no pueden avanzar
                }
            }
        }
    }

    private function guardarCae(array $f, array $c): void
    {
        App::$db->exec("UPDATE facturas SET cae = :c, cae_vto = :v, cae_estado = 'autorizada', cae_error = NULL WHERE id = :id", ['c' => $c['cae'], 'v' => $c['vto'], 'id' => $f['id']]);
    }

    private function rechazar(array $f, string $msg): void
    {
        App::$db->exec("UPDATE facturas SET cae_estado = 'rechazada', cae_error = :e WHERE id = :id", ['e' => $msg, 'id' => $f['id']]);
        $this->aviso('bad', "ARCA rechazó la factura {$f['letra']} {$f['nro']}: $msg");
        Historial::log(App::$db, 'ARCA', "Factura {$f['letra']} {$f['nro']} rechazada: $msg", 'sistema');
    }

    // ------------------------------------------------------------------ mails

    /** Genera el PDF adjunto del mail. */
    public static function adjunto(?array $a): ?array
    {
        if (!$a) {
            return null;
        }
        switch ($a['tipo']) {
            case 'factura':
                $f = Caja::factura((int)$a['id']);
                return ['nombre' => Pdf::nombre("Factura {$f['letra']} {$f['nro']}"), 'tipo' => 'application/pdf', 'contenido' => Comprobantes::pdfFactura($f)];
            case 'recibo':
                $r = Caja::recibo((int)$a['id']);
                return ['nombre' => Pdf::nombre("Recibo {$r['nro']}"), 'tipo' => 'application/pdf', 'contenido' => Comprobantes::pdfRecibo($r)];
            case 'promo':
                $p = Promociones::una((int)$a['id']);
                $t = Padron::titular((int)$a['grupo']);
                return ['nombre' => Pdf::nombre("Promoción - {$p['titulo']}"), 'tipo' => 'application/pdf', 'contenido' => Pdf::generar('info', ['d' => Documentos::infoPromo($p, $t)])];
            case 'resumen':
                $g = Padron::grupo((int)$a['grupo']);
                return ['nombre' => Pdf::nombre('Tus beneficios de ' . Fmt::perLabel($a['periodo'])), 'tipo' => 'application/pdf', 'contenido' => Pdf::generar('info', ['d' => Documentos::infoResumen($g, $a['periodo'])])];
        }
        return null;
    }

    private function mail(array $p): void
    {
        Mailer::enviar($p['para'], $p['asunto'], $p['cuerpo'], self::adjunto($p['adjunto'] ?? null));
        $ref = $p['ref'] ?? null;
        if ($ref && in_array($ref['tabla'] ?? '', ['facturas', 'recibos'], true)) {
            App::$db->exec("UPDATE {$ref['tabla']} SET mail_estado = 'enviado'" . ($ref['tabla'] === 'facturas' ? ', mail_fecha = now()' : '') . ' WHERE id = :id', ['id' => (int)$ref['id']]);
        }
    }

    /** CU-28: un mail rebotado queda marcado y se informa al administrador. */
    private function marcarRebote(array $p, string $err): void
    {
        $ref = $p['ref'] ?? null;
        if ($ref && in_array($ref['tabla'] ?? '', ['facturas', 'recibos'], true)) {
            App::$db->exec("UPDATE {$ref['tabla']} SET mail_estado = 'rebotado' WHERE id = :id", ['id' => (int)$ref['id']]);
        }
        $this->aviso('warn', "El mail a {$p['para']} rebotó: revisá la dirección en la ficha del afiliado. ($err)");
    }

    // ------------------------------------------------------------------ tareas diarias y monitoreo

    /** Revisa el vencimiento del certificado de ARCA (alerta a 30 días) y limpia datos viejos. */
    public function tareaDiaria(): void
    {
        if (Arca::modo() !== 'simulado') {
            $d = ArcaWebService::diasCertificado();
            if ($d !== null && $d < 30) {
                $this->aviso('bad', "El certificado de ARCA vence en $d días: renovalo para seguir facturando (docs/ARCA.md).");
            }
        }
        App::$db->exec("DELETE FROM sesiones WHERE ultima_actividad < now() - interval '1 day'");
        App::$db->exec("DELETE FROM intentos_ingreso WHERE fecha < now() - interval '30 days'");
        App::$db->exec("DELETE FROM tokens_admin WHERE creado < now() - interval '1 day'");
        App::$db->exec("DELETE FROM cola_tareas WHERE estado = 'hecha' AND actualizado < now() - interval '90 days'");
    }

    private function heartbeat(?string $url): void
    {
        if (!$url) {
            return;
        }
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10]);
        curl_exec($ch);
        curl_close($ch);
    }
}
