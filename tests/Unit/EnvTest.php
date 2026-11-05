<?php
declare(strict_types=1);

namespace Tests\Unit;

use App\Core\Env;
use PHPUnit\Framework\TestCase;

/** Despliegue con una sola variable (DATABASE_URL), por ejemplo en EasyPanel. */
final class EnvTest extends TestCase
{
    private const VARS = ['DATABASE_URL', 'DB_HOST', 'DB_PORT', 'DB_NAME', 'DB_OWNER_USER', 'DB_OWNER_PASSWORD', 'DB_PASSWORD', 'DB_BACKUP_PASSWORD'];

    /** @var array<string, string|false> */
    private array $antes = [];

    protected function setUp(): void
    {
        foreach (self::VARS as $v) {
            $this->antes[$v] = getenv($v);
            putenv($v);
        }
    }

    protected function tearDown(): void
    {
        foreach ($this->antes as $v => $valor) {
            putenv($valor === false ? $v : "$v=$valor");
        }
    }

    public function testDatosDeConexionDesdeLaUrl(): void
    {
        putenv('DATABASE_URL=postgres://postgres:cl%40ve%3Adue%C3%B1o@integral_db:5433/integral');
        self::assertSame('integral_db', Env::get('DB_HOST'));
        self::assertSame('5433', Env::get('DB_PORT'));
        self::assertSame('integral', Env::get('DB_NAME'));
        self::assertSame('postgres', Env::get('DB_OWNER_USER'));
        self::assertSame('cl@ve:dueño', Env::get('DB_OWNER_PASSWORD'));
    }

    public function testClavesInternasDerivadasYEstables(): void
    {
        putenv('DATABASE_URL=postgres://postgres:secreta@db/integral');
        $app = Env::get('DB_PASSWORD');
        $copias = Env::get('DB_BACKUP_PASSWORD');
        self::assertSame(40, strlen((string)$app));
        self::assertNotSame($app, $copias);
        self::assertSame($app, Env::get('DB_PASSWORD'));
        self::assertStringNotContainsString('secreta', (string)$app);

        putenv('DATABASE_URL=postgres://postgres:otra@db/integral');
        self::assertNotSame($app, Env::get('DB_PASSWORD'));
    }

    public function testLoDefinidoTienePrioridad(): void
    {
        putenv('DATABASE_URL=postgres://postgres:secreta@db/integral');
        putenv('DB_HOST=otro_host');
        putenv('DB_PASSWORD=explicita');
        self::assertSame('otro_host', Env::get('DB_HOST'));
        self::assertSame('explicita', Env::get('DB_PASSWORD'));
    }

    public function testSinUrlNoHayValoresDeducidos(): void
    {
        self::assertNull(Env::get('DB_HOST'));
        self::assertNull(Env::get('DB_PASSWORD'));
        self::assertSame('5432', Env::get('DB_PORT', '5432'));
        putenv('DATABASE_URL=mysql://u:p@h/b');
        self::assertNull(Env::get('DB_HOST'));
    }
}
