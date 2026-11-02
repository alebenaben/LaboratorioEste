<?php
declare(strict_types=1);

namespace App\Domain;

use App\Core\App;
use App\Core\Config;

/**
 * Plantillas de mail (CU-48) y cola de envíos (ADR-06). Los mails los manda el worker
 * desde sistema@integraldeleste.com.ar con el PDF generado en el servidor.
 */
final class Mails
{
    public const NOMBRES = ['factura' => 'Factura de la cuota', 'recibo' => 'Recibo de pago', 'resumen' => 'Resumen mensual de beneficios', 'promo' => 'Promociones'];
    public const VARIABLES = [
        'factura' => ['nombre', 'periodo', 'importe', 'vencimiento', 'plan', 'numero'],
        'recibo' => ['nombre', 'periodo', 'importe', 'vencimiento', 'plan', 'numero'],
        'resumen' => ['nombre', 'periodo', 'importe', 'detalle'],
        'promo' => ['nombre', 'titulo', 'beneficio', 'lugar', 'texto', 'desde', 'hasta'],
    ];

    /** @return array{asunto:string,cuerpo:string} */
    public static function plantilla(string $k): array
    {
        $m = Config::get('mail', []);
        return $m[$k] ?? ['asunto' => '', 'cuerpo' => ''];
    }

    public static function reciboAutomatico(): bool
    {
        return (bool)(Config::get('mail', [])['recibo_auto'] ?? false);
    }

    /** Reemplaza {variable}; una variable desconocida queda escrita tal cual. */
    public static function llenar(string $txt, array $v): string
    {
        return (string)preg_replace_callback('/\{(\w+)\}/u', fn($m) => array_key_exists($m[1], $v) ? (string)$v[$m[1]] : $m[0], $txt);
    }

    /** @return array{asunto:string,cuerpo:string} */
    public static function armar(string $k, array $vars): array
    {
        $p = self::plantilla($k);
        return ['asunto' => self::llenar($p['asunto'], $vars), 'cuerpo' => self::llenar($p['cuerpo'], $vars)];
    }

    /**
     * Encola un mail. $adjunto describe el PDF que arma el worker: ['tipo' => 'factura'|'recibo'|'promo'|'resumen', …].
     * $ref permite marcar el resultado (rebote) en el comprobante de origen.
     */
    public static function encolar(string $para, string $asunto, string $cuerpo, ?array $adjunto = null, ?array $ref = null): int
    {
        return (int)App::$db->insert('cola_tareas', [
            'tipo' => 'mail',
            'payload' => json_encode(['para' => $para, 'asunto' => $asunto, 'cuerpo' => $cuerpo, 'adjunto' => $adjunto, 'ref' => $ref], JSON_UNESCAPED_UNICODE),
        ]);
    }

    public static function vars(string $k, array $obj): array
    {
        return match ($k) {
            'factura' => self::varsFactura($obj),
            'recibo' => self::varsRecibo($obj),
            default => [],
        };
    }

    public static function varsFactura(array $f): array
    {
        $t = Padron::titular((int)$f['grupo_id']);
        return ['nombre' => Padron::nomPila($t), 'periodo' => Fmt::perLabel($f['periodo']), 'importe' => Fmt::money($f['importe']), 'vencimiento' => Fmt::fd($f['vto']), 'plan' => Catalogo::plan($f['plan_id'])['nombre'], 'numero' => $f['letra'] . ' ' . Caja::nroFactura($f)];
    }

    public static function varsRecibo(array $r): array
    {
        $t = Padron::titular((int)$r['grupo_id']);
        $f = App::$db->one('SELECT * FROM facturas WHERE id = :id', ['id' => $r['factura_id']]);
        $g = Padron::grupo((int)$r['grupo_id']);
        return ['nombre' => Padron::nomPila($t), 'periodo' => $f ? Fmt::perLabel($f['periodo']) : '', 'importe' => Fmt::money($r['importe']), 'vencimiento' => $f ? Fmt::fd($f['vto']) : '', 'plan' => Catalogo::plan($g['plan_id'])['nombre'], 'numero' => Caja::nroRecibo($r)];
    }
}
