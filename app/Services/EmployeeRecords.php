<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;

/**
 * Database operations for the generic employee-information modules (everything except
 * personal-information, which has its own service because of the photo and identity fields).
 * Table and column names come only from Modules::all(); hyphenated tables are backtick-quoted.
 */
final class EmployeeRecords
{
    private static function module(string $slug): array
    {
        $m = Modules::get($slug);
        if ($m === null || ($m['special'] ?? null) === 'personal') {
            throw new HttpException(404, 'Unknown information section.');
        }
        return $m;
    }

    private static function columns(array $m): string
    {
        $cols = array_map([Database::class, 'ident'], array_merge([$m['pk'], 'User_ID'], array_keys($m['fields'])));
        return implode(', ', array_map(static fn($c) => 't.' . $c, $cols));
    }

    public static function listForUser(string $slug, int $userId): array
    {
        $m = self::module($slug);
        $sql = 'SELECT ' . self::columns($m) . ' FROM ' . Database::ident($m['table']) . ' t WHERE t.`User_ID` = ? ORDER BY '
            . ($m['order'] ?? Database::ident($m['pk']) . ' ASC') . ', t.' . Database::ident($m['pk']) . ' ASC';
        return Database::all($sql, [$userId]);
    }

    /** All rows across employees, with the owner's identifiers (administrators only). */
    public static function listAll(string $slug): array
    {
        $m = self::module($slug);
        $sql = 'SELECT ' . self::columns($m) . ', u.UserName, p.DOJ_ID, pi.Full_Name AS Employee_Name
                  FROM ' . Database::ident($m['table']) . ' t
                  JOIN ems_users u ON u.User_ID = t.User_ID
             LEFT JOIN preload_users p ON p.Preload_Users_ID = u.Preload_Users_ID
             LEFT JOIN `personal-information` pi ON pi.User_ID = t.User_ID
              ORDER BY t.' . Database::ident($m['pk']) . ' DESC';
        return Database::all($sql);
    }

    public static function find(string $slug, int $id): ?array
    {
        $m = self::module($slug);
        return Database::one(
            'SELECT ' . self::columns($m) . ' FROM ' . Database::ident($m['table']) . ' t WHERE t.' . Database::ident($m['pk']) . ' = ?',
            [$id]
        );
    }

    public static function create(string $slug, int $userId, array $data): int
    {
        $m = self::module($slug);
        return Database::transaction(function () use ($m, $userId, $data): int {
            // Lock the owning account row: serializes concurrent inserts for the same employee and
            // guarantees the account still exists for the foreign key.
            $owner = Database::one('SELECT User_ID FROM ems_users WHERE User_ID = ? FOR UPDATE', [$userId]);
            if ($owner === null) {
                throw new HttpException(404, 'Employee account not found.');
            }
            if (!empty($m['single'])) {
                $exists = Database::value('SELECT 1 FROM ' . Database::ident($m['table']) . ' WHERE `User_ID` = ? LIMIT 1', [$userId]);
                if ($exists) {
                    throw new HttpException(409, $m['title'] . ' already exists for this employee. Edit the existing record instead.');
                }
            }
            $cols = array_keys($data);
            $sql = 'INSERT INTO ' . Database::ident($m['table']) . ' (`User_ID`, ' . implode(', ', array_map([Database::class, 'ident'], $cols)) . ')
                    VALUES (?' . str_repeat(', ?', count($cols)) . ')';
            Database::run($sql, array_merge([$userId], array_values($data)));
            return Database::lastId();
        });
    }

    public static function update(string $slug, int $id, array $data): void
    {
        $m = self::module($slug);
        if (!$data) {
            return;
        }
        $set = implode(', ', array_map(static fn($c) => Database::ident($c) . ' = ?', array_keys($data)));
        Database::run(
            'UPDATE ' . Database::ident($m['table']) . ' SET ' . $set . ' WHERE ' . Database::ident($m['pk']) . ' = ?',
            array_merge(array_values($data), [$id])
        );
    }

    public static function delete(string $slug, int $id): void
    {
        $m = self::module($slug);
        Database::run('DELETE FROM ' . Database::ident($m['table']) . ' WHERE ' . Database::ident($m['pk']) . ' = ?', [$id]);
    }

    public static function countForUser(string $slug, int $userId): int
    {
        $m = Modules::get($slug);
        return (int) Database::value('SELECT COUNT(*) FROM ' . Database::ident($m['table']) . ' WHERE `User_ID` = ?', [$userId]);
    }

    /** Next suggested child number for an employee. */
    public static function nextChildNumber(int $userId): int
    {
        return 1 + (int) Database::value('SELECT COALESCE(MAX(`Child_Number`), 0) FROM `children-information` WHERE `User_ID` = ?', [$userId]);
    }
}
