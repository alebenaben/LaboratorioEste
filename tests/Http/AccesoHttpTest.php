<?php
declare(strict_types=1);

namespace Tests\Http;

use App\Core\App;
use App\Core\Request;
use App\Core\Response;
use Tests\Integracion\CasoIntegracion;

/**
 * Pruebas de punta a punta contra el núcleo HTTP (sin servidor):
 * CU-01 a CU-03, permisos por rol y sección (S-06), CSRF y cierre por inactividad.
 */
final class AccesoHttpTest extends CasoIntegracion
{
    private App $app;

    protected function setUp(): void
    {
        parent::setUp();
        App::$usuario = null;
        $this->app = new App(App::$db);
        (require dirname(__DIR__, 2) . '/src/rutas.php')($this->app);
    }

    private function pedir(string $metodo, string $ruta, array $post = [], ?string $tok = null, bool $fetch = true, bool $csrf = true): Response
    {
        $path = parse_url($ruta, PHP_URL_PATH);
        parse_str((string)parse_url($ruta, PHP_URL_QUERY), $query);
        if ($tok && $metodo === 'POST' && $csrf && !isset($post['_csrf'])) {
            $post['_csrf'] = App::$db->val('SELECT csrf FROM sesiones WHERE id = :i', ['i' => hash('sha256', $tok)]);
        }
        $server = ['REMOTE_ADDR' => '10.0.0.' . random_int(1, 200), 'HTTP_USER_AGENT' => 'phpunit'];
        if ($fetch) {
            $server['HTTP_X_REQUESTED_WITH'] = 'fetch';
            $server['HTTP_ACCEPT'] = 'application/json';
        }
        return $this->app->manejar(new Request($metodo, $path, $query, $post, [], $server, $tok ? ['integral_sid' => $tok] : []));
    }

    /** Ingresa y devuelve el token de la cookie. */
    private function ingresar(string $u, string $k): string
    {
        $r = $this->pedir('POST', '/ingreso', ['usuario' => $u, 'clave' => $k]);
        self::assertSame(200, $r->status, $r->body);
        preg_match('/integral_sid=([a-f0-9]+)/', $r->headers['Set-Cookie'], $m);
        return $m[1];
    }

    private function json(Response $r): array
    {
        return json_decode($r->body, true) ?: [];
    }

    /** CU-01: "KATY", "katy" y "Káty" ingresan como el mismo usuario. */
    public function testUsuarioSinMayusculasNiTildes(): void
    {
        foreach (['KATY', 'katy', 'Káty'] as $n) {
            $j = $this->json($this->pedir('POST', '/ingreso', ['usuario' => $n, 'clave' => 'Katy']));
            self::assertTrue($j['ok'], $n);
            self::assertSame('Katy', $j['recordar']['nombre']);
        }
    }

    /** CU-01: pantalla de inicio de cada rol; los comercios no se recuerdan, las unidades sí. */
    public function testInicioPorRol(): void
    {
        self::assertSame('/inicio', $this->json($this->pedir('POST', '/ingreso', ['usuario' => 'Belén', 'clave' => 'Belén']))['redirect']);
        self::assertSame('/afiliados', $this->json($this->pedir('POST', '/ingreso', ['usuario' => 'Santiago', 'clave' => 'Santiago']))['redirect']);
        $u = $this->json($this->pedir('POST', '/ingreso', ['usuario' => 'biobarica', 'clave' => 'Biobárica']));
        self::assertSame('/verificar', $u['redirect']);
        self::assertStringContainsString('unidad', $u['recordar']['etiqueta']);
        $c = $this->json($this->pedir('POST', '/ingreso', ['usuario' => 'sanbenito', 'clave' => 'sanbenito']));
        self::assertSame('/verificar', $c['redirect']);
        self::assertNull($c['recordar']);
    }

    /** CU-01 E1, E2, E3. */
    public function testErroresDeIngreso(): void
    {
        self::assertStringContainsString('No existe el usuario “pepe”', $this->json($this->pedir('POST', '/ingreso', ['usuario' => 'pepe', 'clave' => 'x']))['error']);
        self::assertStringContainsString('no es correcta', $this->json($this->pedir('POST', '/ingreso', ['usuario' => 'Mauro', 'clave' => 'x']))['error']);
        App::$db->exec("UPDATE usuarios SET activo = false WHERE nombre = 'Mari'");
        self::assertStringContainsString('dado de baja', $this->json($this->pedir('POST', '/ingreso', ['usuario' => 'Mari', 'clave' => 'Mari']))['error']);
    }

    /** Límite de 5 intentos fallidos por usuario en 15 minutos. */
    public function testLimiteDeIntentos(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->pedir('POST', '/ingreso', ['usuario' => 'Pablo', 'clave' => 'mal']);
        }
        self::assertStringContainsString('Demasiados intentos', $this->json($this->pedir('POST', '/ingreso', ['usuario' => 'Pablo', 'clave' => 'Pablo']))['error']);
    }

    /** CU-01: con 2 solicitudes pendientes el administrador ve el aviso; un usuario no. */
    public function testAvisoDeAuditoriaAlIngresar(): void
    {
        $t = $this->ingresar('Santiago', 'Santiago');
        $r = $this->pedir('GET', '/afiliados', [], $t, false);
        self::assertStringContainsString('<b>2</b> solicitudes', $r->body);
        $u = $this->ingresar('Belén', 'Belén');
        self::assertStringNotContainsString('Prestaciones para auditar', $this->pedir('GET', '/inicio', [], $u, false)->body);
    }

    /** S-06: un usuario de atención recibe 403 al pedir el padrón; una unidad también. */
    public function testPermisosPorRol(): void
    {
        $u = $this->ingresar('Belén', 'Belén');
        self::assertSame(403, $this->pedir('GET', '/api/afiliados', [], $u)->status);
        self::assertSame(403, $this->pedir('GET', '/caja', [], $u)->status);
        self::assertSame(403, $this->pedir('POST', '/afiliados/1/reactivar', [], $u)->status);
        self::assertSame(403, $this->pedir('POST', '/config/usuarios', ['nombre' => 'x'], $u)->status);
        self::assertSame(200, $this->pedir('GET', '/config', [], $u, false)->status);
        $un = $this->ingresar('Biobárica', 'Biobárica');
        self::assertSame(403, $this->pedir('GET', '/api/afiliados', [], $un)->status);
        self::assertSame(403, $this->pedir('GET', '/autorizaciones', [], $un)->status);
        self::assertSame(200, $this->pedir('GET', '/verificar', [], $un, false)->status);
        $a = $this->ingresar('Katy', 'Katy');
        self::assertSame(200, $this->pedir('GET', '/api/afiliados', [], $a)->status);
        self::assertSame(403, $this->pedir('GET', '/verificar', [], $a)->status);
    }

    /** CU-39: un usuario sin "Cotizador" recibe 403 al pedir la cotización. */
    public function testSeccionesHabilitadas(): void
    {
        App::$db->exec("UPDATE usuarios SET secciones = '{altas}' WHERE nombre = 'Angie'");
        $u = $this->ingresar('Angie', 'Angie');
        self::assertSame(403, $this->pedir('POST', '/cotizador/enviar', ['plan' => 'PLUS', 'nombre' => 'X', 'celular' => '3435013651'], $u)->status);
        self::assertSame(200, $this->pedir('GET', '/altas', [], $u, false)->status);
    }

    /** CU-52: un usuario de atención no puede leer el historial (solo está en la configuración del administrador). */
    public function testHistorialSoloAdministradores(): void
    {
        $u = $this->ingresar('Cecilia', 'Cecilia');
        self::assertStringNotContainsString('Historial de cambios', $this->pedir('GET', '/config', [], $u, false)->body);
        $a = $this->ingresar('Pablo', 'Pablo');
        self::assertStringContainsString('Historial de cambios', $this->pedir('GET', '/config', [], $a, false)->body);
    }

    /** Un POST sin token CSRF se rechaza. */
    public function testCsrf(): void
    {
        $t = $this->ingresar('Santiago', 'Santiago');
        self::assertSame(419, $this->pedir('POST', '/egresos', ['importe' => '100'], $t, true, false)->status);
    }

    /** CU-02: tras 15 minutos sin actividad, la API responde "sesión vencida" y queda en el historial. */
    public function testCierrePorInactividad(): void
    {
        $t = $this->ingresar('Mauro', 'Mauro');
        App::$db->exec("UPDATE sesiones SET ultima_actividad = now() - interval '16 minutes' WHERE id = :i", ['i' => hash('sha256', $t)]);
        $r = $this->pedir('GET', '/api/afiliados', [], $t);
        self::assertSame(401, $r->status);
        self::assertSame('/ingreso?vencida=1', $this->json($r)['redirect']);
        self::assertSame('Sesión de Mauro cerrada por inactividad', App::$db->val("SELECT detalle FROM historial WHERE accion = 'Sesión' ORDER BY id DESC LIMIT 1"));
        $p = $this->pedir('GET', '/afiliados', [], $t, false);
        self::assertStringStartsWith('/ingreso', $p->headers['Location']);
    }

    /** CU-02: salir registra "… salió del sistema" y la sesión deja de servir. */
    public function testSalir(): void
    {
        $t = $this->ingresar('Sofía', 'Sofía');
        $this->pedir('POST', '/salir', [], $t);
        self::assertSame('Sofía salió del sistema', App::$db->val("SELECT detalle FROM historial ORDER BY id DESC LIMIT 1"));
        self::assertSame(401, $this->pedir('GET', '/api/ping', [], $t)->status);
    }

    /** CU-03: con la clave actual mal no cambia; después del cambio la anterior deja de servir. */
    public function testCambiarClave(): void
    {
        $t = $this->ingresar('Mari', 'Mari');
        self::assertStringContainsString('actual no es correcta', $this->json($this->pedir('POST', '/mi-clave', ['actual' => 'x', 'nueva' => 'nueva1', 'repetir' => 'nueva1'], $t))['error']);
        self::assertStringContainsString('no coinciden', $this->json($this->pedir('POST', '/mi-clave', ['actual' => 'Mari', 'nueva' => 'nueva1', 'repetir' => 'otra'], $t))['error']);
        self::assertStringContainsString('4 caracteres', $this->json($this->pedir('POST', '/mi-clave', ['actual' => 'Mari', 'nueva' => 'abc', 'repetir' => 'abc'], $t))['error']);
        self::assertTrue($this->json($this->pedir('POST', '/mi-clave', ['actual' => 'Mari', 'nueva' => 'nueva1', 'repetir' => 'nueva1'], $t))['ok']);
        self::assertFalse($this->json($this->pedir('POST', '/ingreso', ['usuario' => 'Mari', 'clave' => 'Mari']))['ok']);
        self::assertTrue($this->json($this->pedir('POST', '/ingreso', ['usuario' => 'Mari', 'clave' => 'nueva1']))['ok']);
    }

    /** S-06: con la clave inicial, todo lleva a "Cambiar mi clave". */
    public function testClaveInicialObligatoria(): void
    {
        App::$db->exec("UPDATE usuarios SET debe_cambiar_clave = true WHERE nombre = 'Pablo'");
        $t = $this->ingresar('Pablo', 'Pablo');
        $r = $this->pedir('GET', '/afiliados', [], $t, false);
        self::assertSame('/mi-clave', $r->headers['Location']);
        self::assertSame(403, $this->pedir('GET', '/api/afiliados', [], $t)->status);
    }

    /** CU-04 por HTTP: el pedido devuelve "requiereAdmin" y vuelve a funcionar con el token. */
    public function testAutorizacionDeAdministradorPorHttp(): void
    {
        $t = $this->ingresar('Belén', 'Belén');
        $j = $this->json($this->pedir('POST', '/api/autorizar', ['usuario' => 'Katy', 'clave' => 'mala', 'accion' => 'montos'], $t));
        self::assertFalse($j['ok']);
        $j = $this->json($this->pedir('POST', '/api/autorizar', ['usuario' => 'Katy', 'clave' => 'Katy', 'accion' => 'montos'], $t));
        self::assertTrue($j['ok']);
        self::assertSame(64 - 16, strlen($j['token']));
    }

    public function testSalud(): void
    {
        $j = $this->json($this->pedir('GET', '/salud'));
        self::assertTrue($j['ok']);
    }

    /** Las pantallas principales responden sin errores para cada rol. */
    public function testPantallasResponden(): void
    {
        $a = $this->ingresar('Santiago', 'Santiago');
        foreach (['/afiliados', '/altas', '/cotizador', '/autorizaciones', '/auditoria', '/prestaciones', '/beneficios', '/retencion?q=75', '/caja', '/egresos', '/informes?tipo=usos&p=anio', '/cuentas?g=1', '/contabilidad', '/estadisticas', '/promociones', '/promociones?enviar=1', '/config', '/afiliados/1/ficha', '/afiliados/1/ficha?editar=1', '/afiliados/1/historial', '/afiliados/1/baja'] as $ruta) {
            $r = $this->pedir('GET', $ruta, [], $a, false);
            self::assertSame(200, $r->status, "$ruta: " . substr(strip_tags($r->body), 0, 300));
        }
        $u = $this->ingresar('Biobárica', 'Biobárica');
        $r = $this->pedir('GET', '/verificar/unidad?q=90000501', [], $u, false);
        self::assertStringContainsString('SIN CARGO', $r->body);
        $c = $this->ingresar('sanbenito', 'sanbenito');
        $r = $this->pedir('GET', '/verificar/comercio?q=28913458', [], $c, false);
        self::assertStringContainsString('Tiene el beneficio', $r->body);
    }
}
