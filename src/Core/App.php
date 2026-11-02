<?php
declare(strict_types=1);

namespace App\Core;

use App\Domain\Permisos;

/**
 * Núcleo de la aplicación: contexto del pedido, enrutador y manejo de errores.
 */
final class App
{
    public static Db $db;
    public static ?Request $req = null;
    public static ?Sesion $sesion = null;
    public static ?array $usuario = null;
    /** Administrador que autorizó la acción en curso (CU-04). */
    public static ?string $autorizo = null;

    /** @var array<int,array{0:string,1:string,2:callable|array,3:array}> */
    private array $rutas = [];

    public function __construct(?Db $db = null)
    {
        self::$db = $db ?? Db::get();
    }

    public function ruta(string $metodo, string $patron, callable|array $handler, array $opc = []): self
    {
        $this->rutas[] = [$metodo, $patron, $handler, $opc];
        return $this;
    }

    public function get(string $p, callable|array $h, array $o = []): self
    {
        return $this->ruta('GET', $p, $h, $o);
    }

    public function post(string $p, callable|array $h, array $o = []): self
    {
        return $this->ruta('POST', $p, $h, $o);
    }

    public function manejar(Request $req): Response
    {
        self::$req = $req;
        self::$usuario = null;
        self::$autorizo = null;
        $ses = new Sesion(self::$db, $req);
        self::$sesion = $ses;
        try {
            $ses->cargar();
            self::$usuario = $ses->usuario;
            [$h, $opc] = $this->resolver($req);
            $publica = $opc['publica'] ?? false;
            if (!$publica) {
                if (!$ses->usuario) {
                    throw new HttpError(401, $ses->vencida ? 'Se cerró la sesión por inactividad. Volvé a ingresar.' : 'Ingresá con tu usuario y clave.');
                }
                if (empty($opc['sin_actividad'])) {
                    $ses->tocar();
                }
                if ($req->method === 'POST' && !hash_equals($ses->csrf(), (string)($req->post['_csrf'] ?? $req->server['HTTP_X_CSRF'] ?? ''))) {
                    throw new HttpError(419);
                }
                // Primer ingreso: hay que cambiar la clave antes de seguir (S-06).
                if ($ses->usuario['debe_cambiar_clave'] && empty($opc['libre_clave'])) {
                    if ($req->esFetch() || $req->method === 'POST') {
                        throw new HttpError(403, 'Antes de seguir tenés que cambiar tu clave inicial.');
                    }
                    return Response::redirect('/mi-clave');
                }
                if (isset($opc['seccion']) && !Permisos::puedeVer($ses->usuario, $opc['seccion'])) {
                    throw new HttpError(403);
                }
                if (!empty($opc['admin']) && !$ses->usuario['admin']) {
                    throw new HttpError(403, 'Solo un administrador puede hacer esto.');
                }
                if (isset($opc['roles']) && !in_array($ses->usuario['rol'], $opc['roles'], true)) {
                    throw new HttpError(403);
                }
            }
            $res = is_array($h) ? (new $h[0]())->{$h[1]}($req) : $h($req);
            return $res instanceof Response ? $res : Response::html((string)$res);
        } catch (HttpError $e) {
            return $this->error($req, $e);
        } catch (\Throwable $e) {
            error_log('[integral] ' . get_class($e) . ': ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine());
            if (Env::get('APP_DEBUG') === '1' || Env::esPrueba()) {
                $msg = $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine();
            } else {
                $msg = 'Ocurrió un error inesperado. Volvé a intentarlo y, si sigue, avisale a sistemas.';
            }
            return $this->error($req, new HttpError(500, $msg));
        }
    }

    /** @return array{0:callable|array,1:array} */
    private function resolver(Request $req): array
    {
        $existe = false;
        foreach ($this->rutas as [$m, $p, $h, $o]) {
            $re = '#^' . preg_replace('#\{(\w+)\}#', '(?P<$1>[^/]+)', $p) . '$#';
            if (preg_match($re, $req->path, $mm)) {
                $existe = true;
                if ($m !== $req->method) {
                    continue;
                }
                foreach ($mm as $k => $v) {
                    if (!is_int($k)) {
                        $req->params[$k] = urldecode($v);
                    }
                }
                return [$h, $o];
            }
        }
        throw new HttpError($existe ? 405 : 404);
    }

    private function error(Request $req, HttpError $e): Response
    {
        if ($req->esFetch() || $req->method === 'POST') {
            $d = ['ok' => false, 'error' => $e->getMessage(), 'codigo' => $e->codigo];
            if ($e instanceof Falta) {
                $d['campos'] = $e->campos;
            }
            if ($e instanceof RequiereAdmin) {
                $d['requiereAdmin'] = $e->accion;
                $d['texto'] = $e->getMessage();
            }
            if ($e->codigo === 401) {
                $d['redirect'] = '/ingreso?vencida=1';
            }
            $r = Response::json($d, $e->codigo);
            if ($e->codigo === 401) {
                $r->headers['Set-Cookie'] = self::$sesion?->cookieHeader(null) ?? '';
            }
            return $r;
        }
        if ($e->codigo === 401) {
            $r = Response::redirect('/ingreso' . (self::$sesion?->vencida ? '?vencida=1' : ''));
            $r->headers['Set-Cookie'] = self::$sesion?->cookieHeader(null) ?? '';
            return $r;
        }
        $html = View::render('error', ['codigo' => $e->codigo, 'mensaje' => $e->getMessage()], self::$usuario ? 'layout' : 'layout_simple');
        return Response::html($html, $e->codigo);
    }
}

/** CU-04: la acción necesita la clave de un administrador. */
final class RequiereAdmin extends HttpError
{
    public function __construct(public readonly string $accion, string $texto)
    {
        parent::__construct(403, $texto);
    }
}
