<?php
declare(strict_types=1);

namespace App\Core;

use PDO;
use PDOException;
use PDOStatement;

/**
 * Thin PDO wrapper. Every query goes through prepared statements; identifiers
 * (table/column names) are only ever taken from server-side whitelists and quoted with ident().
 */
final class Database
{
    private static ?PDO $pdo = null;

    public static function pdo(): PDO
    {
        if (self::$pdo === null) {
            $dsn = sprintf(
                'mysql:host=%s;port=%d;dbname=%s;charset=%s',
                Config::get('db.host', '127.0.0.1'),
                (int) Config::get('db.port', 3306),
                Config::get('db.name', 'doj-employee'),
                Config::get('db.charset', 'utf8mb4')
            );
            $options = [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES   => false,
                PDO::ATTR_STRINGIFY_FETCHES  => false,
            ];
            // TLS for hosted MySQL services (Aiven, TiDB Cloud, etc.); set via DB_SSL / DB_SSL_CA.
            $sslCa = Config::get('db.ssl_ca');
            if (is_string($sslCa) && $sslCa !== '') {
                $caAttr = defined('Pdo\Mysql::ATTR_SSL_CA') ? \Pdo\Mysql::ATTR_SSL_CA : PDO::MYSQL_ATTR_SSL_CA;
                $options[$caAttr] = $sslCa;
            }
            self::$pdo = new PDO($dsn, (string) Config::get('db.user'), (string) Config::get('db.pass'), $options);
            $tz = (string) Config::get('db.time_zone', '');
            if ($tz !== '' && preg_match('/^[+-]\d{2}:\d{2}$/', $tz)) {
                self::$pdo->exec("SET time_zone = '" . $tz . "'");
            }
        }
        return self::$pdo;
    }

    public static function run(string $sql, array $params = []): PDOStatement
    {
        $stmt = self::pdo()->prepare($sql);
        foreach ($params as $key => $value) {
            $name = is_int($key) ? $key + 1 : (str_starts_with($key, ':') ? $key : ':' . $key);
            $type = match (true) {
                is_int($value)  => PDO::PARAM_INT,
                is_bool($value) => PDO::PARAM_BOOL,
                $value === null => PDO::PARAM_NULL,
                default         => PDO::PARAM_STR,
            };
            $stmt->bindValue($name, $value, $type);
        }
        $stmt->execute();
        return $stmt;
    }

    public static function one(string $sql, array $params = []): ?array
    {
        $row = self::run($sql, $params)->fetch();
        return $row === false ? null : $row;
    }

    public static function all(string $sql, array $params = []): array
    {
        return self::run($sql, $params)->fetchAll();
    }

    public static function value(string $sql, array $params = []): mixed
    {
        $v = self::run($sql, $params)->fetchColumn();
        return $v === false ? null : $v;
    }

    public static function lastId(): int
    {
        return (int) self::pdo()->lastInsertId();
    }

    /**
     * Run a callback inside a transaction. Rolls back on any exception and re-throws.
     *
     * @template T
     * @param callable():T $fn
     * @return T
     */
    public static function transaction(callable $fn): mixed
    {
        $pdo = self::pdo();
        if ($pdo->inTransaction()) {
            return $fn();
        }
        $pdo->beginTransaction();
        try {
            $result = $fn();
            $pdo->commit();
            return $result;
        } catch (\Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            throw $e;
        }
    }

    /** Quote a whitelisted identifier, e.g. `personal-information`. Rejects anything unexpected. */
    public static function ident(string $name): string
    {
        if (!preg_match('/^[A-Za-z0-9_-]{1,64}$/', $name)) {
            throw new \InvalidArgumentException('Invalid SQL identifier.');
        }
        return '`' . $name . '`';
    }

    public static function isDuplicateKey(\Throwable $e): bool
    {
        return $e instanceof PDOException && (($e->errorInfo[1] ?? null) === 1062 || str_contains($e->getMessage(), 'Duplicate'));
    }

    public static function isForeignKeyViolation(\Throwable $e): bool
    {
        return $e instanceof PDOException && in_array($e->errorInfo[1] ?? null, [1451, 1452], true);
    }

    /** Name of the unique index named in a duplicate-key error message, if any. */
    public static function duplicateKeyName(\Throwable $e): ?string
    {
        if (preg_match("/for key '(?:[^'.]*\\.)?([^']+)'/i", $e->getMessage(), $m)) {
            return $m[1];
        }
        return null;
    }
}
