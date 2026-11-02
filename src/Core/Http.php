<?php
declare(strict_types=1);

namespace App\Core;

/** Pedido HTTP. */
final class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $post = [],
        public readonly array $files = [],
        public readonly array $server = [],
        public readonly array $cookies = [],
        public array $params = [],
    ) {
    }

    public static function desdeGlobales(): self
    {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $path = '/' . trim($path, '/');
        $post = $_POST;
        $ct = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($ct, 'application/json')) {
            $post = json_decode((string)file_get_contents('php://input'), true) ?: [];
        }
        return new self(strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'), $path, $_GET, $post, $_FILES, $_SERVER, $_COOKIE);
    }

    public function get(string $k, mixed $def = null): mixed
    {
        return $this->post[$k] ?? $this->query[$k] ?? $this->params[$k] ?? $def;
    }

    public function str(string $k, string $def = ''): string
    {
        $v = $this->get($k, $def);
        return is_array($v) ? $def : trim((string)$v);
    }

    public function int(string $k, int $def = 0): int
    {
        $v = $this->get($k);
        return is_numeric($v) ? (int)$v : $def;
    }

    public function arr(string $k): array
    {
        $v = $this->get($k, []);
        return is_array($v) ? $v : [];
    }

    public function bool(string $k): bool
    {
        $v = $this->get($k);
        return in_array($v, ['1', 'on', 'true', 'si', true, 1], true);
    }

    public function ip(): string
    {
        $xf = $this->server['HTTP_X_FORWARDED_FOR'] ?? '';
        if ($xf !== '' && Env::bool('TRUST_PROXY', true)) {
            return trim(explode(',', $xf)[0]);
        }
        return $this->server['REMOTE_ADDR'] ?? '0.0.0.0';
    }

    public function esFetch(): bool
    {
        return ($this->server['HTTP_X_REQUESTED_WITH'] ?? '') === 'fetch'
            || str_contains($this->server['HTTP_ACCEPT'] ?? '', 'application/json');
    }

    public function esHttps(): bool
    {
        return ($this->server['HTTPS'] ?? '') === 'on' || ($this->server['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
    }
}

/** Respuesta HTTP. */
final class Response
{
    public array $headers = [];

    public function __construct(public string $body = '', public int $status = 200, array $headers = [])
    {
        $this->headers = $headers + ['Content-Type' => 'text/html; charset=utf-8'];
    }

    public static function html(string $html, int $status = 200): self
    {
        return new self($html, $status);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        return new self(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), $status, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public static function redirect(string $url, int $status = 303): self
    {
        return new self('', $status, ['Location' => $url]);
    }

    public static function archivo(string $contenido, string $nombre, string $tipo, bool $descargar = true): self
    {
        $disp = ($descargar ? 'attachment' : 'inline') . "; filename=\"" . addcslashes(Archivo::ascii($nombre), '"') . "\"; filename*=UTF-8''" . rawurlencode($nombre);
        return new self($contenido, 200, ['Content-Type' => $tipo, 'Content-Disposition' => $disp, 'Cache-Control' => 'no-store']);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $k => $v) {
            header("$k: $v");
        }
        echo $this->body;
    }
}

final class Archivo
{
    public static function ascii(string $s): string
    {
        $t = \App\Domain\Fmt::norm($s);
        $o = preg_replace('/[^A-Za-z0-9 ._()-]/', '', strtr($s, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ñ' => 'n', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ñ' => 'N', '—' => '-', '·' => '-']));
        return $o ?: $t;
    }
}

/** Error con código HTTP (403, 404, 409, 422…). */
class HttpError extends \RuntimeException
{
    public function __construct(public readonly int $codigo, string $mensaje = '')
    {
        parent::__construct($mensaje ?: match ($codigo) {
            403 => 'No tenés permiso para hacer esto.',
            404 => 'No se encontró lo que buscabas.',
            401 => 'La sesión venció. Volvé a ingresar.',
            419 => 'El formulario venció. Volvé a intentarlo.',
            default => 'Error',
        });
    }
}

/** Error de validación que se muestra al usuario. */
final class Falta extends HttpError
{
    public function __construct(string $mensaje, public readonly array $campos = [])
    {
        parent::__construct(422, $mensaje);
    }
}
