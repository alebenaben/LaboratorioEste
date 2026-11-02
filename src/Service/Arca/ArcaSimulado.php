<?php
declare(strict_types=1);

namespace App\Service\Arca;

use App\Core\App;
use App\Domain\Fechas;

/**
 * CAE simulado (como genCAE de la maqueta): sirve para pruebas y para la demo.
 * Los números autorizados se recuerdan en la tabla numeradores ("ARCA-SIM-<pv>-<tipo>").
 */
final class ArcaSimulado implements Facturador
{
    public function autorizar(array $c): array
    {
        $ult = $this->ultimoAutorizado($c['pv'], $c['tipo']);
        if ($c['numero'] !== $ult + 1) {
            throw new ArcaRechazo("El número {$c['numero']} no es el siguiente al último autorizado ($ult).");
        }
        if ($c['total'] <= 0) {
            throw new ArcaRechazo('El importe tiene que ser mayor que cero.');
        }
        App::$db->exec(
            'INSERT INTO numeradores (clave, ultimo) VALUES (:k, :n) ON CONFLICT (clave) DO UPDATE SET ultimo = EXCLUDED.ultimo',
            ['k' => $this->clave($c['pv'], $c['tipo']), 'n' => $c['numero']]
        );
        $cae = '76' . str_pad((string)random_int(0, 999999999999), 12, '0', STR_PAD_LEFT);
        return ['cae' => $cae, 'vto' => Fechas::sumarDias($c['fecha'], 10)];
    }

    public function ultimoAutorizado(int $pv, int $tipo): int
    {
        return (int)(App::$db->val('SELECT ultimo FROM numeradores WHERE clave = :k', ['k' => $this->clave($pv, $tipo)]) ?? $this->minimo($tipo));
    }

    /** Arranca desde el último número configurado (CU-46). */
    private function minimo(int $tipo): int
    {
        $letra = $tipo === 1 ? 'A' : 'B';
        return (int)(\App\Core\Config::get('ultimos_numeros', [])[$letra] ?? 0);
    }

    public function consultar(int $pv, int $tipo, int $numero): ?array
    {
        return null;
    }

    private function clave(int $pv, int $tipo): string
    {
        return "ARCA-SIM-$pv-$tipo";
    }
}
