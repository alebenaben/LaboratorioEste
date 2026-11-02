<?php
declare(strict_types=1);

namespace App\Core;

/** Plantillas PHP en views/. */
final class View
{
    public static function dir(): string
    {
        return dirname(__DIR__, 2) . '/views';
    }

    /** Renderiza una plantilla. Con $layout envuelve el resultado en esa plantilla ($contenido). */
    public static function render(string $tpl, array $vars = [], ?string $layout = 'layout'): string
    {
        $html = self::parcial($tpl, $vars);
        if ($layout === null) {
            return $html;
        }
        return self::parcial($layout, $vars + ['contenido' => $html, 'vista' => $vars['vista'] ?? '']);
    }

    public static function parcial(string $tpl, array $vars = []): string
    {
        $archivo = self::dir() . '/' . $tpl . '.php';
        if (!is_file($archivo)) {
            throw new \RuntimeException("Falta la plantilla $tpl");
        }
        extract($vars, EXTR_SKIP);
        ob_start();
        try {
            include $archivo;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string)ob_get_clean();
    }
}
