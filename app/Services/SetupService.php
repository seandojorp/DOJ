<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Audit;
use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;

/**
 * First-run administrator setup.
 *
 * "Initialized" is durable and does not depend on an administrator still being active. The system
 * counts as initialized when ANY of the following is true:
 *   1. storage/setup.lock exists (written after successful setup);
 *   2. audit_logs contains a successful SYSTEM_INITIALIZED event;
 *   3. ems_users contains any account with Role = 'admin' (active or not).
 * Deactivating or even deleting administrators therefore never reopens /setup.
 *
 * Concurrency: setup runs under a MariaDB named lock (GET_LOCK) plus a local file lock, inside a
 * transaction that re-checks the state, so simultaneous submissions create at most one administrator.
 * No schema change is required.
 */
final class SetupService
{
    private const LOCK_NAME = 'doj_ems_first_run_setup';

    public static function lockFile(): string
    {
        return STORAGE_PATH . '/setup.lock';
    }

    public static function isInitialized(): bool
    {
        if (is_file(self::lockFile())) {
            return true;
        }
        $initialized = (bool) Database::value(
            "SELECT 1 FROM audit_logs WHERE Action = 'SYSTEM_INITIALIZED' AND Action_Result = 'success' LIMIT 1"
        ) || (bool) Database::value("SELECT 1 FROM ems_users WHERE Role = 'admin' LIMIT 1");

        if ($initialized) {
            self::writeLockFile(); // self-heal the marker file if it was removed
        }
        return $initialized;
    }

    public static function createAdministrator(string $username, string $password): int
    {
        $fh = @fopen(STORAGE_PATH . '/setup.mutex', 'c');
        if ($fh === false || !flock($fh, LOCK_EX)) {
            throw new HttpException(503, 'Setup is busy. Please try again.');
        }
        try {
            $got = (int) Database::value('SELECT GET_LOCK(?, 10)', [self::LOCK_NAME]);
            if ($got !== 1) {
                throw new HttpException(503, 'Setup is busy. Please try again.');
            }
            try {
                $userId = Database::transaction(function () use ($username, $password): int {
                    if (self::isInitialized()) {
                        throw new HttpException(409, 'The system has already been initialized.');
                    }
                    if (Database::value('SELECT 1 FROM ems_users WHERE UserName = ?', [$username])) {
                        throw new \App\Core\ValidationException(['username' => 'This username is already taken.']);
                    }
                    Database::run(
                        "INSERT INTO ems_users (Preload_Users_ID, UserName, password_hash, Role, Account_Status)
                         VALUES (NULL, ?, ?, 'admin', 'active')",
                        [$username, Auth::hash($password)]
                    );
                    $id = Database::lastId();
                    Database::run(
                        "INSERT INTO audit_logs (Actor_User_ID, Actor_Username, Actor_Role, Action, Affected_Table, Affected_Record_ID, Action_Result, IP_Address)
                         VALUES (?, ?, 'admin', 'SYSTEM_INITIALIZED', 'ems_users', ?, 'success', ?)",
                        [$id, $username, (string) $id, \App\Core\Request::clientIp()]
                    );
                    return $id;
                });
            } finally {
                Database::value('SELECT RELEASE_LOCK(?)', [self::LOCK_NAME]);
            }
            self::writeLockFile();
            Audit::log('ACCOUNT_CREATE_ADMIN_SETUP', 'ems_users', $userId, 'success', ['id' => $userId, 'username' => $username, 'role' => 'admin']);
            return $userId;
        } finally {
            flock($fh, LOCK_UN);
            fclose($fh);
        }
    }

    private static function writeLockFile(): void
    {
        $file = self::lockFile();
        if (!is_file($file)) {
            @file_put_contents($file, 'initialized=' . date('c') . PHP_EOL, LOCK_EX);
        }
    }
}
