<?php
declare(strict_types=1);

namespace App\Core;

use SessionHandlerInterface;
use SessionUpdateTimestampHandlerInterface;

/**
 * Stores PHP sessions in the `ems_sessions` table instead of on disk.
 *
 * Needed on serverless hosts such as Vercel, where each request may run on a different instance
 * with its own temporary disk, so file-based sessions would be lost between requests.
 * Create the table with database/migrations/003_sessions_table.sql.
 */
final class DatabaseSessionHandler implements SessionHandlerInterface, SessionUpdateTimestampHandlerInterface
{
    private const TABLE = '`ems_sessions`';

    public function open(string $path, string $name): bool
    {
        return true;
    }

    public function close(): bool
    {
        return true;
    }

    public function read(string $id): string|false
    {
        $data = Database::value(
            'SELECT Data FROM ' . self::TABLE . ' WHERE Session_ID = ? AND Expires_At > ?',
            [$id, time()]
        );
        return $data === null ? '' : (string) $data;
    }

    public function write(string $id, string $data): bool
    {
        Database::run(
            'INSERT INTO ' . self::TABLE . ' (Session_ID, Data, Expires_At) VALUES (?, ?, ?)
             ON DUPLICATE KEY UPDATE Data = VALUES(Data), Expires_At = VALUES(Expires_At)',
            [$id, $data, $this->expiry()]
        );
        return true;
    }

    public function destroy(string $id): bool
    {
        Database::run('DELETE FROM ' . self::TABLE . ' WHERE Session_ID = ?', [$id]);
        return true;
    }

    public function gc(int $max_lifetime): int|false
    {
        return Database::run('DELETE FROM ' . self::TABLE . ' WHERE Expires_At <= ?', [time()])->rowCount();
    }

    /** Used by session.use_strict_mode: only accept IDs that already exist. */
    public function validateId(string $id): bool
    {
        return Database::value(
            'SELECT 1 FROM ' . self::TABLE . ' WHERE Session_ID = ? AND Expires_At > ?',
            [$id, time()]
        ) !== null;
    }

    public function updateTimestamp(string $id, string $data): bool
    {
        Database::run(
            'UPDATE ' . self::TABLE . ' SET Expires_At = ? WHERE Session_ID = ?',
            [$this->expiry(), $id]
        );
        return true;
    }

    private function expiry(): int
    {
        return time() + max(1800, (int) ini_get('session.gc_maxlifetime'));
    }
}
