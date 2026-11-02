<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOStatement;

/** Acceso a PostgreSQL con PDO y algunos atajos. */
final class Db
{
    private static ?Db $inst = null;
    private int $nivelTx = 0;

    public function __construct(public readonly PDO $pdo)
    {
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
    }

    public static function conectar(?string $usuario = null, ?string $clave = null, ?string $base = null): self
    {
        $host = Env::get('DB_HOST', '127.0.0.1');
        $port = Env::get('DB_PORT', '5432');
        $base ??= Env::get('DB_NAME', 'integral');
        $usuario ??= Env::get('DB_USER', 'integral_app');
        $clave ??= Env::get('DB_PASSWORD', '');
        $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$base", $usuario, $clave, [PDO::ATTR_TIMEOUT => 5]);
        $tz = Env::get('TZ', 'America/Argentina/Buenos_Aires');
        $pdo->exec("SET TIME ZONE " . $pdo->quote($tz));
        return new self($pdo);
    }

    public static function get(): self
    {
        return self::$inst ??= self::conectar();
    }

    public static function set(?Db $db): void
    {
        self::$inst = $db;
    }

    public function q(string $sql, array $p = []): PDOStatement
    {
        $st = $this->pdo->prepare($sql);
        foreach ($p as $k => $v) {
            $key = is_int($k) ? $k + 1 : (str_starts_with($k, ':') ? $k : ":$k");
            $tipo = match (true) {
                is_int($v) => PDO::PARAM_INT,
                is_bool($v) => PDO::PARAM_BOOL,
                $v === null => PDO::PARAM_NULL,
                default => PDO::PARAM_STR,
            };
            if (is_array($v)) {
                $v = self::arrayPg($v);
            }
            $st->bindValue($key, $v, $tipo);
        }
        $st->execute();
        return $st;
    }

    /** @return array<int,array<string,mixed>> */
    public function all(string $sql, array $p = []): array
    {
        return $this->q($sql, $p)->fetchAll();
    }

    /** @return array<string,mixed>|null */
    public function one(string $sql, array $p = []): ?array
    {
        $r = $this->q($sql, $p)->fetch();
        return $r === false ? null : $r;
    }

    public function val(string $sql, array $p = []): mixed
    {
        $r = $this->q($sql, $p)->fetchColumn();
        return $r === false ? null : $r;
    }

    /** @return array<int,mixed> */
    public function col(string $sql, array $p = []): array
    {
        return $this->q($sql, $p)->fetchAll(PDO::FETCH_COLUMN);
    }

    public function exec(string $sql, array $p = []): int
    {
        return $this->q($sql, $p)->rowCount();
    }

    /** INSERT … RETURNING id */
    public function insert(string $tabla, array $datos, string $ret = 'id'): mixed
    {
        $cols = array_keys($datos);
        $sql = 'INSERT INTO ' . $tabla . ' (' . implode(', ', $cols) . ') VALUES (' . implode(', ', array_map(fn($c) => ':' . $c, $cols)) . ') RETURNING ' . $ret;
        return $this->val($sql, $datos);
    }

    public function update(string $tabla, array $datos, string $where, array $p = []): int
    {
        $set = implode(', ', array_map(fn($c) => "$c = :$c", array_keys($datos)));
        return $this->exec("UPDATE $tabla SET $set WHERE $where", $datos + $p);
    }

    /**
     * Ejecuta en una transacción. Anidable: solo la exterior confirma.
     * @template T
     * @param callable():T $fn
     * @return T
     */
    public function tx(callable $fn, string $aislamiento = ''): mixed
    {
        $sp = null;
        if ($this->nivelTx === 0) {
            $this->pdo->beginTransaction();
            if ($aislamiento) {
                $this->pdo->exec("SET TRANSACTION ISOLATION LEVEL $aislamiento");
            }
        } else {
            // Transacción anidada: un punto de guardado permite deshacer solo esta parte.
            $sp = 'sp' . $this->nivelTx;
            $this->pdo->exec("SAVEPOINT $sp");
        }
        $this->nivelTx++;
        try {
            $r = $fn();
            $this->nivelTx--;
            if ($this->nivelTx === 0) {
                $this->pdo->commit();
            } elseif ($sp) {
                $this->pdo->exec("RELEASE SAVEPOINT $sp");
            }
            return $r;
        } catch (\Throwable $e) {
            $this->nivelTx--;
            if ($this->nivelTx === 0 && $this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            } elseif ($sp && $this->pdo->inTransaction()) {
                $this->pdo->exec("ROLLBACK TO SAVEPOINT $sp");
            }
            throw $e;
        }
    }

    /** Pruebas: abre una transacción externa que se deshace al terminar cada prueba. */
    public function iniciarPrueba(): void
    {
        $this->pdo->beginTransaction();
        $this->nivelTx = 1;
    }

    public function terminarPrueba(): void
    {
        if ($this->pdo->inTransaction()) {
            $this->pdo->rollBack();
        }
        $this->nivelTx = 0;
    }

    public function enTx(): bool
    {
        return $this->nivelTx > 0;
    }

    /** Convierte un array PHP en literal de array de PostgreSQL. */
    public static function arrayPg(array $a): string
    {
        return '{' . implode(',', array_map(fn($x) => '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], (string)$x) . '"', $a)) . '}';
    }

    /** Lee un text[] de PostgreSQL. */
    public static function leerArray(?string $s): array
    {
        if ($s === null || $s === '{}' || $s === '') {
            return [];
        }
        $s = substr($s, 1, -1);
        preg_match_all('/"((?:[^"\\\\]|\\\\.)*)"|([^,]+)/', $s, $m, PREG_SET_ORDER);
        return array_map(fn($x) => isset($x[2]) && $x[2] !== '' ? $x[2] : stripcslashes($x[1]), $m);
    }
}
