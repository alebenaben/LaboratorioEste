<?php
declare(strict_types=1);

use App\Core\App;
use App\Domain\Fmt;

/** Atajos para las plantillas. */

function e(mixed $s): string
{
    return Fmt::e($s);
}

function money(mixed $n): string
{
    return Fmt::money((float)$n);
}

function fd(?string $s): string
{
    return Fmt::fd($s);
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(App::$sesion?->csrf()) . '">';
}

function yo(): ?array
{
    return App::$usuario;
}

function es_admin(): bool
{
    return (bool)(App::$usuario['admin'] ?? false);
}

function sel(mixed $a, mixed $b): string
{
    return (string)$a === (string)$b ? ' selected' : '';
}

function chk(mixed $v): string
{
    return $v ? ' checked' : '';
}

/** Ícono SVG del menú (mismos trazos que la maqueta). */
function icono(string $id, int $tam = 0): string
{
    static $IC = [
        'verificar' => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
        'auditoria' => '<path d="M9 5H7a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2h-2"/><rect x="9" y="3" width="6" height="4" rx="1"/><path d="m9 14 2 2 4-4"/>',
        'beneficios' => '<path d="M20 12v9H4v-9"/><path d="M2 7h20v5H2zM12 22V7"/><path d="M12 7H7.5a2.5 2.5 0 1 1 0-5C11 2 12 7 12 7ZM12 7h4.5a2.5 2.5 0 1 0 0-5C13 2 12 7 12 7Z"/>',
        'inicio' => '<path d="M3 11 12 4l9 7"/><path d="M5 10v10h14V10"/><path d="M10 20v-6h4v6"/>',
        'afiliados' => '<path d="M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8Zm-7 9a7 7 0 0 1 14 0M17 11a3 3 0 1 0 0-6M22 20a5 5 0 0 0-4-5"/>',
        'cotizador' => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M8 6h8M8 11h2M12 11h2M16 11v6M8 15h2M12 15h2M8 19h6"/>',
        'informes' => '<path d="M6 2h9l5 5v15H6z"/><path d="M9 17v-3M12 17v-6M15 17v-4"/>',
        'egresos' => '<path d="M12 3v12M7 10l5 5 5-5"/><path d="M4 21h16"/>',
        'caja' => '<rect x="2" y="6" width="20" height="13" rx="2"/><path d="M2 10h20M6 15h4"/>',
        'cuentas' => '<path d="M4 4h16v16H4zM4 9h16M9 9v11"/>',
        'autorizaciones' => '<path d="M12 3 4 6v6c0 5 3.5 8 8 9 4.5-1 8-4 8-9V6l-8-3Z"/><path d="m8.5 12 2.5 2.5 4.5-5"/>',
        'contabilidad' => '<path d="M5 3h11l3 3v15H5z"/><path d="M9 9h6M9 13h6M9 17h4"/>',
        'estadisticas' => '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
        'retencion' => '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="4"/><path d="m5.6 5.6 3.6 3.6M14.8 14.8l3.6 3.6M14.8 9.2l3.6-3.6M5.6 18.4l3.6-3.6"/>',
        'promociones' => '<path d="M3 11v2a1 1 0 0 0 1 1h2l6 4V6L6 10H4a1 1 0 0 0-1 1Z"/><path d="M16 8.5a4.5 4.5 0 0 1 0 7M19 5.5a9 9 0 0 1 0 13"/>',
        'altas' => '<path d="M15 19a6 6 0 0 0-12 0M9 11a4 4 0 1 0 0-8 4 4 0 0 0 0 8ZM19 8v6M16 11h6"/>',
        'prestaciones' => '<path d="M6 2h9l5 5v15H6z"/><path d="M14 2v6h6M9 13h6M9 17h3M15 17h1"/>',
        'config' => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.7 1.7 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.7 1.7 0 0 0-1.8-.3 1.7 1.7 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.7 1.7 0 0 0-1.1-1.5 1.7 1.7 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.7 1.7 0 0 0 .3-1.8 1.7 1.7 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.7 1.7 0 0 0 1.5-1.1 1.7 1.7 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.7 1.7 0 0 0 1.8.3H9a1.7 1.7 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.7 1.7 0 0 0 1 1.5 1.7 1.7 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.7 1.7 0 0 0-.3 1.8V9a1.7 1.7 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.7 1.7 0 0 0-1.5 1Z"/>',
    ];
    $w = $tam ? " width=\"$tam\" height=\"$tam\"" : '';
    return '<svg viewBox="0 0 24 24"' . $w . ' fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . ($IC[$id] ?? '') . '</svg>';
}

function logo_svg(): string
{
    return '<svg viewBox="0 0 150 156" aria-hidden="true"><rect x="48" y="0" width="40" height="156" fill="currentColor"/><rect x="0" y="58" width="48" height="40" fill="currentColor"/><polygon points="98,53 145,61 148,93 98,100" fill="var(--logo-red)"/></svg>';
}

/** Encabezado de pantalla como en la maqueta. */
function head(string $titulo, string $sub, string $extra = ''): string
{
    return '<div class="head"><div><h1>' . $titulo . '</h1><p>' . $sub . '</p></div><div class="row">' . $extra . '</div></div>';
}

function chip(string $texto, string $tipo = 'neu'): string
{
    return '<span class="chip ' . e($tipo) . '">' . e($texto) . '</span>';
}

/** Chip del estado de cuenta (RT-05). */
function chip_cuenta(string $estado): string
{
    return match ($estado) {
        'vencida' => chip('Deuda vencida', 'bad'),
        'por_vencer' => chip('Cuota por vencer', 'warn'),
        default => chip('Al día', 'ok'),
    };
}

function hoy(): string
{
    return \App\Domain\Fechas::hoy();
}
