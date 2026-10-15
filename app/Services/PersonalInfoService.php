<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Database;
use App\Core\HttpException;
use App\Core\ValidationException;

/**
 * personal-information: one row per account (UNIQUE User_ID), Photo is a NOT NULL LONGBLOB.
 *
 * Registration identity (cellphone and email) is owned by preload_users. For accounts linked to a
 * preloaded employee, Mobile_No and Email_Address are always written from preload_users, whatever the
 * browser submits — so a regular user cannot change them through this form. Administrators change
 * them through Registered Accounts, which re-synchronizes this table (syncIdentity()).
 */
final class PersonalInfoService
{
    private static function columns(): array
    {
        return array_keys(Modules::get('personal')['fields']);
    }

    public static function get(int $userId): ?array
    {
        $cols = implode(', ', array_map([Database::class, 'ident'], self::columns()));
        $row = Database::one(
            'SELECT `Personal_ID`, `User_ID`, ' . $cols . ', (`Photo` IS NOT NULL AND LENGTH(`Photo`) > 0) AS has_photo
               FROM `personal-information` WHERE `User_ID` = ?',
            [$userId]
        );
        if ($row) {
            $row['has_photo'] = (bool) $row['has_photo'];
        }
        return $row;
    }

    public static function photo(int $userId): ?string
    {
        $v = Database::value('SELECT `Photo` FROM `personal-information` WHERE `User_ID` = ?', [$userId]);
        return is_string($v) && $v !== '' ? $v : null;
    }

    /** Registration identity for an account, or null for accounts without a preloaded record. */
    public static function identity(int $userId): ?array
    {
        return Database::one(
            'SELECT p.Cellphone_No, p.Email_Address FROM ems_users u JOIN preload_users p ON p.Preload_Users_ID = u.Preload_Users_ID WHERE u.User_ID = ?',
            [$userId]
        );
    }

    /**
     * Insert or update the personal record.
     *
     * @return array{id:int, created:bool}
     */
    public static function save(int $userId, array $input, ?string $photoBytes): array
    {
        $identity = self::identity($userId);
        if ($identity !== null) {
            // Locked fields: always taken from the registration record.
            $input['Mobile_No'] = $identity['Cellphone_No'];
            $input['Email_Address'] = $identity['Email_Address'];
        }
        [$data, $errors] = Modules::validate('personal', $input);
        if ($errors) {
            throw new ValidationException($errors);
        }

        return Database::transaction(function () use ($userId, $data, $photoBytes): array {
            $owner = Database::one('SELECT User_ID FROM ems_users WHERE User_ID = ? FOR UPDATE', [$userId]);
            if ($owner === null) {
                throw new HttpException(404, 'Employee account not found.');
            }
            $existing = Database::one('SELECT `Personal_ID` FROM `personal-information` WHERE `User_ID` = ? FOR UPDATE', [$userId]);

            if ($existing === null) {
                if ($photoBytes === null) {
                    throw new ValidationException(['Photo' => 'A photo is required.']);
                }
                $cols = array_keys($data);
                Database::run(
                    'INSERT INTO `personal-information` (`User_ID`, `Photo`, ' . implode(', ', array_map([Database::class, 'ident'], $cols)) . ')
                     VALUES (?, ?' . str_repeat(', ?', count($cols)) . ')',
                    array_merge([$userId, $photoBytes], array_values($data))
                );
                return ['id' => Database::lastId(), 'created' => true];
            }

            if ($photoBytes !== null) {
                $data['Photo'] = $photoBytes;
            }
            $set = implode(', ', array_map(static fn($c) => Database::ident($c) . ' = ?', array_keys($data)));
            Database::run('UPDATE `personal-information` SET ' . $set . ' WHERE `User_ID` = ?', array_merge(array_values($data), [$userId]));
            return ['id' => (int) $existing['Personal_ID'], 'created' => false];
        });
    }

    /** Copy the registration cellphone/email into personal-information after an admin edits them. */
    public static function syncIdentity(int $userId): void
    {
        $identity = self::identity($userId);
        if ($identity !== null) {
            Database::run(
                'UPDATE `personal-information` SET `Mobile_No` = ?, `Email_Address` = ? WHERE `User_ID` = ?',
                [$identity['Cellphone_No'], $identity['Email_Address'], $userId]
            );
        }
    }

    public static function delete(int $userId): void
    {
        Database::run('DELETE FROM `personal-information` WHERE `User_ID` = ?', [$userId]);
    }

    /** All personal records (without photos) for the administrator overview. */
    public static function listAll(): array
    {
        return Database::all(
            'SELECT pi.`Personal_ID`, pi.`User_ID`, pi.`Full_Name`, pi.`Birthday`, pi.`Sex`, pi.`Civil_Status`, pi.`Mobile_No`, pi.`Email_Address`,
                    u.UserName, p.DOJ_ID
               FROM `personal-information` pi
               JOIN ems_users u ON u.User_ID = pi.User_ID
          LEFT JOIN preload_users p ON p.Preload_Users_ID = u.Preload_Users_ID
           ORDER BY pi.`Full_Name`'
        );
    }
}
