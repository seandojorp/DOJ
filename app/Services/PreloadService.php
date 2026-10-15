<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Core\Validator;

/**
 * preload_users CRUD. Registration status is always derived from ems_users (never stored).
 * DOJ_ID, Cellphone_No and Email_Address each have their own UNIQUE index.
 */
final class PreloadService
{
    private const KEY_FIELDS = [
        'uq_preload_doj_id'    => 'DOJ_ID',
        'uq_preload_cellphone' => 'Cellphone_No',
        'uq_preload_email'     => 'Email_Address',
    ];

    /**
     * Normalize and validate identity input.
     * @return array{0: array, 1: array} [data, errors]
     */
    public static function normalize(array $input): array
    {
        $errors = [];
        $data = [];

        $raw = trim((string) ($input['DOJ_ID'] ?? ''));
        if ($raw === '') {
            $errors['DOJ_ID'] = 'DOJ ID is required.';
        } elseif (($v = Validator::dojId($raw)) === null) {
            $errors['DOJ_ID'] = 'DOJ ID may contain letters, numbers, dash, slash, dot or underscore (max 50).';
        } else {
            $data['DOJ_ID'] = $v;
        }

        $raw = trim((string) ($input['Cellphone_No'] ?? ''));
        if ($raw === '') {
            $errors['Cellphone_No'] = 'Cellphone number is required.';
        } elseif (($v = Validator::phMobile($raw)) === null) {
            $errors['Cellphone_No'] = 'Enter a valid Philippine mobile number (e.g. 09171234567 or +639171234567).';
        } else {
            $data['Cellphone_No'] = $v;
        }

        $raw = trim((string) ($input['Email_Address'] ?? ''));
        if ($raw === '') {
            $errors['Email_Address'] = 'Email address is required.';
        } elseif (($v = Validator::email($raw)) === null) {
            $errors['Email_Address'] = 'Enter a valid email address.';
        } elseif (mb_strlen($v) > 50) {
            $errors['Email_Address'] = 'Email address must not exceed 50 characters.';
        } else {
            $data['Email_Address'] = $v;
        }
        return [$data, $errors];
    }

    /** Field-level uniqueness errors for a normalized record (optionally excluding one record). */
    public static function duplicateErrors(array $data, ?int $excludeId = null): array
    {
        $labels = ['DOJ_ID' => 'DOJ ID', 'Cellphone_No' => 'cellphone number', 'Email_Address' => 'email address'];
        $errors = [];
        foreach ($labels as $col => $label) {
            if (!isset($data[$col])) {
                continue;
            }
            $sql = 'SELECT 1 FROM preload_users WHERE ' . Database::ident($col) . ' = ?' . ($excludeId ? ' AND Preload_Users_ID <> ?' : '') . ' LIMIT 1';
            if (Database::value($sql, $excludeId ? [$data[$col], $excludeId] : [$data[$col]])) {
                $errors[$col] = 'This ' . $label . ' is already assigned to another employee.';
            }
        }
        return $errors;
    }

    public static function duplicateKeyError(\Throwable $e): ValidationException
    {
        $key = Database::duplicateKeyName($e);
        $field = self::KEY_FIELDS[$key] ?? null;
        foreach (self::KEY_FIELDS as $k => $f) {
            if ($field === null && str_contains($e->getMessage(), $k)) {
                $field = $f;
            }
        }
        return new ValidationException([$field ?? 'DOJ_ID' => 'This value is already assigned to another employee.']);
    }

    public static function list(?string $status = null): array
    {
        $where = match ($status) {
            'registered'   => 'WHERE u.User_ID IS NOT NULL',
            'unregistered' => 'WHERE u.User_ID IS NULL',
            default        => '',
        };
        return Database::all(
            "SELECT p.Preload_Users_ID, p.DOJ_ID, p.Cellphone_No, p.Email_Address,
                    u.User_ID, u.UserName, u.Account_Status, u.Created_At AS Registered_At,
                    CASE WHEN u.User_ID IS NULL THEN 'unregistered' ELSE 'registered' END AS Registration_Status
               FROM preload_users p
          LEFT JOIN ems_users u ON u.Preload_Users_ID = p.Preload_Users_ID
             $where
           ORDER BY p.DOJ_ID"
        );
    }

    public static function find(int $id): ?array
    {
        return Database::one(
            "SELECT p.Preload_Users_ID, p.DOJ_ID, p.Cellphone_No, p.Email_Address,
                    u.User_ID, u.UserName, u.Role, u.Account_Status, u.Created_At AS Registered_At, u.Last_Login,
                    CASE WHEN u.User_ID IS NULL THEN 'unregistered' ELSE 'registered' END AS Registration_Status
               FROM preload_users p
          LEFT JOIN ems_users u ON u.Preload_Users_ID = p.Preload_Users_ID
              WHERE p.Preload_Users_ID = ?",
            [$id]
        );
    }

    public static function stats(): array
    {
        $r = Database::one(
            'SELECT COUNT(*) AS total, COUNT(u.User_ID) AS registered
               FROM preload_users p LEFT JOIN ems_users u ON u.Preload_Users_ID = p.Preload_Users_ID'
        );
        $total = (int) $r['total'];
        $registered = (int) $r['registered'];
        return ['total' => $total, 'registered' => $registered, 'unregistered' => $total - $registered];
    }

    public static function create(array $input): int
    {
        [$data, $errors] = self::normalize($input);
        $errors += $data ? self::duplicateErrors($data) : [];
        if ($errors) {
            throw new ValidationException($errors);
        }
        try {
            Database::run('INSERT INTO preload_users (DOJ_ID, Cellphone_No, Email_Address) VALUES (?, ?, ?)',
                [$data['DOJ_ID'], $data['Cellphone_No'], $data['Email_Address']]);
            return Database::lastId();
        } catch (\PDOException $e) {
            if (Database::isDuplicateKey($e)) {
                throw self::duplicateKeyError($e);
            }
            throw $e;
        }
    }

    public static function update(int $id, array $input): void
    {
        if (!self::find($id)) {
            throw new HttpException(404, 'Preloaded employee not found.');
        }
        [$data, $errors] = self::normalize($input);
        $errors += $data ? self::duplicateErrors($data, $id) : [];
        if ($errors) {
            throw new ValidationException($errors);
        }
        try {
            Database::transaction(function () use ($id, $data): void {
                Database::run('UPDATE preload_users SET DOJ_ID = ?, Cellphone_No = ?, Email_Address = ? WHERE Preload_Users_ID = ?',
                    [$data['DOJ_ID'], $data['Cellphone_No'], $data['Email_Address'], $id]);
                $uid = Database::value('SELECT User_ID FROM ems_users WHERE Preload_Users_ID = ?', [$id]);
                if ($uid) {
                    PersonalInfoService::syncIdentity((int) $uid);
                }
            });
        } catch (\PDOException $e) {
            if (Database::isDuplicateKey($e)) {
                throw self::duplicateKeyError($e);
            }
            throw $e;
        }
    }

    /**
     * Delete an unregistered preloaded employee. Records linked to an account are never deleted here:
     * the caller receives HTTP 409 and should offer account deactivation instead.
     */
    public static function delete(int $id): void
    {
        Database::transaction(function () use ($id): void {
            $row = Database::one('SELECT Preload_Users_ID FROM preload_users WHERE Preload_Users_ID = ? FOR UPDATE', [$id]);
            if ($row === null) {
                throw new HttpException(404, 'Preloaded employee not found.');
            }
            $linked = Database::one('SELECT User_ID, UserName FROM ems_users WHERE Preload_Users_ID = ?', [$id]);
            if ($linked) {
                throw new HttpException(409, 'This employee has a registered account (' . $linked['UserName'] . '). '
                    . 'Linked records cannot be deleted. Deactivate the account instead.', ['linked_user_id' => (int) $linked['User_ID']]);
            }
            try {
                Database::run('DELETE FROM preload_users WHERE Preload_Users_ID = ?', [$id]);
            } catch (\PDOException $e) {
                if (Database::isForeignKeyViolation($e)) {
                    throw new HttpException(409, 'This employee is linked to an account and cannot be deleted. Deactivate the account instead.');
                }
                throw $e;
            }
        });
    }
}
