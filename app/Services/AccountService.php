<?php
declare(strict_types=1);

namespace App\Services;

use App\Core\Auth;
use App\Core\Database;
use App\Core\HttpException;
use App\Core\ValidationException;
use App\Core\Validator;

/**
 * ems_users management: self-registration, administrator account management, password changes.
 *
 * Integrity rules enforced here (the schema alone cannot express them):
 *   - a 'user' account must always be linked to a real preload_users row (Preload_Users_ID NOT NULL);
 *   - only administrators may have Preload_Users_ID = NULL;
 *   - at least one active administrator must always remain.
 */
final class AccountService
{
    public const GENERIC_IDENTITY_ERROR = 'We could not verify your employee details, or an account already exists for this employee. '
        . 'Check that your DOJ ID, cellphone number and email address exactly match your HR record, or contact the system administrator.';

    public const EMPLOYEE_TABLES = [
        'personal-information', 'family_background', 'children-information', 'educational-background',
        'civil_service_eligibility', 'work_experience', 'voluntary_work', 'learning_development',
        'employment-information', 'other_information',
    ];

    public static function find(int $id): ?array
    {
        return Database::one(
            'SELECT u.User_ID, u.Preload_Users_ID, u.UserName, u.Role, u.Account_Status, u.Created_At, u.Updated_At, u.Last_Login,
                    p.DOJ_ID, p.Cellphone_No, p.Email_Address, pi.Full_Name
               FROM ems_users u
          LEFT JOIN preload_users p ON p.Preload_Users_ID = u.Preload_Users_ID
          LEFT JOIN `personal-information` pi ON pi.User_ID = u.User_ID
              WHERE u.User_ID = ?',
            [$id]
        );
    }

    public static function list(): array
    {
        return Database::all(
            'SELECT u.User_ID, u.Preload_Users_ID, u.UserName, u.Role, u.Account_Status, u.Created_At, u.Last_Login,
                    p.DOJ_ID, p.Cellphone_No, p.Email_Address, pi.Full_Name
               FROM ems_users u
          LEFT JOIN preload_users p ON p.Preload_Users_ID = u.Preload_Users_ID
          LEFT JOIN `personal-information` pi ON pi.User_ID = u.User_ID
           ORDER BY u.Created_At DESC, u.User_ID DESC'
        );
    }

    public static function stats(): array
    {
        $r = Database::one(
            "SELECT COUNT(*) AS total,
                    SUM(Account_Status = 'active') AS active,
                    SUM(Account_Status = 'inactive') AS inactive,
                    SUM(Role = 'admin') AS admins
               FROM ems_users"
        );
        return array_map('intval', $r);
    }

    private static function validateUsername(string $raw, array &$errors): ?string
    {
        if ($raw === '') {
            $errors['username'] = 'Username is required.';
            return null;
        }
        $u = Validator::username($raw);
        if ($u === null) {
            $errors['username'] = 'Username must be 4–50 characters: letters, numbers, dot, dash or underscore, starting with a letter or number.';
        }
        return $u;
    }

    /**
     * Public self-registration. Succeeds only if DOJ_ID, cellphone and email all match ONE preload_users row
     * that has no account yet. Runs in a transaction with the preload row locked; the UNIQUE index on
     * ems_users.Preload_Users_ID is the final guard against simultaneous registrations.
     */
    public static function register(array $in): int
    {
        $errors = [];
        $doj = Validator::dojId((string) ($in['doj_id'] ?? ''));
        $cell = Validator::phMobile((string) ($in['cellphone'] ?? ''));
        $email = Validator::email((string) ($in['email'] ?? ''));
        if (trim((string) ($in['doj_id'] ?? '')) === '') $errors['doj_id'] = 'DOJ ID is required.';
        elseif ($doj === null) $errors['doj_id'] = 'Enter a valid DOJ ID.';
        if (trim((string) ($in['cellphone'] ?? '')) === '') $errors['cellphone'] = 'Cellphone number is required.';
        elseif ($cell === null) $errors['cellphone'] = 'Enter a valid Philippine mobile number (e.g. 09171234567).';
        if (trim((string) ($in['email'] ?? '')) === '') $errors['email'] = 'Email address is required.';
        elseif ($email === null) $errors['email'] = 'Enter a valid email address.';

        $username = self::validateUsername(trim((string) ($in['username'] ?? '')), $errors);
        $errors += Auth::validateNewPassword((string) ($in['password'] ?? ''), (string) ($in['confirm_password'] ?? ''));
        if ($errors) {
            throw new ValidationException($errors);
        }

        try {
            return Database::transaction(function () use ($doj, $cell, $email, $username, $in): int {
                $preload = Database::one(
                    'SELECT Preload_Users_ID FROM preload_users WHERE DOJ_ID = ? AND Cellphone_No = ? AND Email_Address = ? FOR UPDATE',
                    [$doj, $cell, $email]
                );
                if ($preload === null || Database::value('SELECT 1 FROM ems_users WHERE Preload_Users_ID = ?', [$preload['Preload_Users_ID']])) {
                    throw new HttpException(422, self::GENERIC_IDENTITY_ERROR, ['_identity' => 'mismatch']);
                }
                if (Database::value('SELECT 1 FROM ems_users WHERE UserName = ?', [$username])) {
                    throw new ValidationException(['username' => 'This username is already taken. Please choose another.']);
                }
                Database::run(
                    "INSERT INTO ems_users (Preload_Users_ID, UserName, password_hash, Role, Account_Status) VALUES (?, ?, ?, 'user', 'active')",
                    [(int) $preload['Preload_Users_ID'], $username, Auth::hash((string) $in['password'])]
                );
                return Database::lastId();
            });
        } catch (\PDOException $e) {
            if (Database::isDuplicateKey($e)) {
                if (str_contains($e->getMessage(), 'username') || Database::duplicateKeyName($e) === 'username') {
                    throw new ValidationException(['username' => 'This username is already taken. Please choose another.']);
                }
                throw new HttpException(422, self::GENERIC_IDENTITY_ERROR, ['_identity' => 'mismatch']);
            }
            throw $e;
        }
    }

    /** Administrator creates an account (a 'user' linked to an unregistered preload row, or an 'admin'). */
    public static function adminCreate(array $in): int
    {
        $errors = [];
        $role = ($in['role'] ?? 'user') === 'admin' ? 'admin' : 'user';
        $username = self::validateUsername(trim((string) ($in['username'] ?? '')), $errors);
        $preloadId = Validator::intVal($in['preload_id'] ?? '', 1, PHP_INT_MAX);
        if ($role === 'user' && $preloadId === null) {
            $errors['preload_id'] = 'Select the preloaded employee this account belongs to.';
        }
        $errors += Auth::validateNewPassword((string) ($in['password'] ?? ''), (string) ($in['confirm_password'] ?? ''));
        if ($errors) {
            throw new ValidationException($errors);
        }
        try {
            return Database::transaction(function () use ($role, $username, $preloadId, $in): int {
                if ($preloadId !== null) {
                    if (!Database::one('SELECT 1 FROM preload_users WHERE Preload_Users_ID = ? FOR UPDATE', [$preloadId])) {
                        throw new ValidationException(['preload_id' => 'The selected employee no longer exists.']);
                    }
                    if (Database::value('SELECT 1 FROM ems_users WHERE Preload_Users_ID = ?', [$preloadId])) {
                        throw new ValidationException(['preload_id' => 'This employee already has an account.']);
                    }
                }
                if (Database::value('SELECT 1 FROM ems_users WHERE UserName = ?', [$username])) {
                    throw new ValidationException(['username' => 'This username is already taken.']);
                }
                Database::run(
                    "INSERT INTO ems_users (Preload_Users_ID, UserName, password_hash, Role, Account_Status) VALUES (?, ?, ?, ?, 'active')",
                    [$preloadId, $username, Auth::hash((string) $in['password']), $role]
                );
                return Database::lastId();
            });
        } catch (\PDOException $e) {
            if (Database::isDuplicateKey($e)) {
                throw new ValidationException(['username' => 'This username or employee is already registered.']);
            }
            throw $e;
        }
    }

    /**
     * Administrator edits account details: username, role, and (for linked accounts) the registration
     * identity in preload_users. The account stays linked to the same preload record.
     */
    public static function adminUpdate(int $id, array $in, int $actorId): array
    {
        $errors = [];
        $username = self::validateUsername(trim((string) ($in['username'] ?? '')), $errors);
        $role = in_array($in['role'] ?? '', ['admin', 'user'], true) ? $in['role'] : null;
        if ($role === null) {
            $errors['role'] = 'Select a valid role.';
        }
        $current = self::find($id);
        if ($current === null) {
            throw new HttpException(404, 'Account not found.');
        }
        $identity = null;
        if ($current['Preload_Users_ID'] !== null) {
            [$identity, $idErrors] = PreloadService::normalize([
                'DOJ_ID' => $in['doj_id'] ?? '', 'Cellphone_No' => $in['cellphone'] ?? '', 'Email_Address' => $in['email'] ?? '',
            ]);
            $map = ['DOJ_ID' => 'doj_id', 'Cellphone_No' => 'cellphone', 'Email_Address' => 'email'];
            foreach ($idErrors + ($identity ? PreloadService::duplicateErrors($identity, (int) $current['Preload_Users_ID']) : []) as $k => $msg) {
                $errors[$map[$k]] = $msg;
            }
        }
        if ($role === 'user' && $current['Preload_Users_ID'] === null) {
            $errors['role'] = 'This administrator is not linked to a preloaded employee record, so it cannot become a regular user.';
        }
        if ($id === $actorId && $role !== null && $role !== $current['Role']) {
            $errors['role'] = 'You cannot change your own role.';
        }
        if ($errors) {
            throw new ValidationException($errors);
        }

        $changed = [];
        try {
            Database::transaction(function () use ($id, $current, $username, $role, $identity, &$changed): void {
                if ($current['Role'] === 'admin' && $role === 'user' && $current['Account_Status'] === 'active') {
                    self::assertAnotherActiveAdmin($id);
                }
                if ($username !== $current['UserName']) {
                    if (Database::value('SELECT 1 FROM ems_users WHERE UserName = ? AND User_ID <> ?', [$username, $id])) {
                        throw new ValidationException(['username' => 'This username is already taken.']);
                    }
                    $changed[] = 'username';
                }
                if ($role !== $current['Role']) {
                    $changed[] = 'role';
                }
                Database::run('UPDATE ems_users SET UserName = ?, Role = ? WHERE User_ID = ?', [$username, $role, $id]);

                if ($identity !== null) {
                    foreach (['DOJ_ID' => 'doj_id', 'Cellphone_No' => 'cellphone', 'Email_Address' => 'email'] as $col => $label) {
                        if ((string) $identity[$col] !== (string) $current[$col]) {
                            $changed[] = $label;
                        }
                    }
                    Database::run('UPDATE preload_users SET DOJ_ID = ?, Cellphone_No = ?, Email_Address = ? WHERE Preload_Users_ID = ?',
                        [$identity['DOJ_ID'], $identity['Cellphone_No'], $identity['Email_Address'], (int) $current['Preload_Users_ID']]);
                    PersonalInfoService::syncIdentity($id);
                }
            });
        } catch (\PDOException $e) {
            if (Database::isDuplicateKey($e)) {
                $ve = PreloadService::duplicateKeyError($e);
                $map = ['DOJ_ID' => 'doj_id', 'Cellphone_No' => 'cellphone', 'Email_Address' => 'email'];
                $out = [];
                foreach ($ve->errors as $k => $m) {
                    $out[$map[$k] ?? 'username'] = $m;
                }
                throw new ValidationException($out);
            }
            throw $e;
        }
        return $changed;
    }

    public static function setStatus(int $id, string $status, int $actorId): void
    {
        if (!in_array($status, ['active', 'inactive'], true)) {
            throw new ValidationException(['status' => 'Invalid status.']);
        }
        if ($id === $actorId && $status === 'inactive') {
            throw new HttpException(422, 'You cannot deactivate your own account.');
        }
        Database::transaction(function () use ($id, $status): void {
            $row = Database::one('SELECT Role, Account_Status FROM ems_users WHERE User_ID = ? FOR UPDATE', [$id]);
            if ($row === null) {
                throw new HttpException(404, 'Account not found.');
            }
            if ($status === 'inactive' && $row['Role'] === 'admin' && $row['Account_Status'] === 'active') {
                self::assertAnotherActiveAdmin($id);
            }
            Database::run('UPDATE ems_users SET Account_Status = ? WHERE User_ID = ?', [$status, $id]);
        });
    }

    public static function resetPassword(int $id, string $password, string $confirm): void
    {
        $errors = Auth::validateNewPassword($password, $confirm);
        if ($errors) {
            throw new ValidationException($errors);
        }
        $n = Database::run('UPDATE ems_users SET password_hash = ? WHERE User_ID = ?', [Auth::hash($password), $id])->rowCount();
        if ($n === 0 && !self::find($id)) {
            throw new HttpException(404, 'Account not found.');
        }
    }

    /** Signed-in user changes their own password. Returns the new hash (to refresh the session fingerprint). */
    public static function changeOwnPassword(int $id, string $current, string $new, string $confirm): string
    {
        $hash = (string) Database::value('SELECT password_hash FROM ems_users WHERE User_ID = ?', [$id]);
        if ($hash === '' || !password_verify($current, $hash)) {
            throw new ValidationException(['current_password' => 'Your current password is incorrect.']);
        }
        $errors = Auth::validateNewPassword($new, $confirm, 'new_password', 'confirm_password');
        if (!$errors && hash_equals($current, $new)) {
            $errors['new_password'] = 'Your new password must be different from your current password.';
        }
        if ($errors) {
            throw new ValidationException($errors);
        }
        $newHash = Auth::hash($new);
        Database::run('UPDATE ems_users SET password_hash = ? WHERE User_ID = ?', [$newHash, $id]);
        return $newHash;
    }

    /** Number of employee-information rows linked to an account, per table. */
    public static function linkedRecordCounts(int $id): array
    {
        $parts = [];
        foreach (self::EMPLOYEE_TABLES as $t) {
            $parts[] = 'SELECT ' . Database::pdo()->quote($t) . ' AS t, COUNT(*) AS c FROM ' . Database::ident($t) . ' WHERE `User_ID` = ?';
        }
        $out = [];
        foreach (Database::all(implode(' UNION ALL ', $parts), array_fill(0, count(self::EMPLOYEE_TABLES), $id)) as $r) {
            if ((int) $r['c'] > 0) {
                $out[$r['t']] = (int) $r['c'];
            }
        }
        return $out;
    }

    /**
     * Delete an account only when it owns no employee records (ON DELETE RESTRICT is honored, never bypassed).
     * The preload_users row is kept, so the employee simply becomes "Not Registered" again.
     */
    public static function delete(int $id, int $actorId): void
    {
        if ($id === $actorId) {
            throw new HttpException(422, 'You cannot delete your own account.');
        }
        Database::transaction(function () use ($id): void {
            $row = Database::one('SELECT Role, Account_Status FROM ems_users WHERE User_ID = ? FOR UPDATE', [$id]);
            if ($row === null) {
                throw new HttpException(404, 'Account not found.');
            }
            if ($row['Role'] === 'admin' && $row['Account_Status'] === 'active') {
                self::assertAnotherActiveAdmin($id);
            }
            $linked = self::linkedRecordCounts($id);
            if ($linked) {
                throw new HttpException(409, 'This account has employee records (' . array_sum($linked) . ' rows) and cannot be deleted. Deactivate it instead.',
                    ['linked' => $linked]);
            }
            try {
                Database::run('DELETE FROM ems_users WHERE User_ID = ?', [$id]);
            } catch (\PDOException $e) {
                if (Database::isForeignKeyViolation($e)) {
                    throw new HttpException(409, 'This account has linked employee records and cannot be deleted. Deactivate it instead.');
                }
                throw $e;
            }
        });
    }

    /** Throws unless an active administrator other than $exceptId exists. Locks the admin rows. */
    private static function assertAnotherActiveAdmin(int $exceptId): void
    {
        $others = Database::all(
            "SELECT User_ID FROM ems_users WHERE Role = 'admin' AND Account_Status = 'active' AND User_ID <> ? FOR UPDATE",
            [$exceptId]
        );
        if (count($others) === 0) {
            throw new HttpException(422, 'This is the last active administrator. Create or activate another administrator first.');
        }
    }

    public static function recentRegistrations(int $limit = 8): array
    {
        return Database::all(
            "SELECT u.User_ID, u.UserName, u.Created_At, u.Account_Status, p.DOJ_ID, pi.Full_Name
               FROM ems_users u
               JOIN preload_users p ON p.Preload_Users_ID = u.Preload_Users_ID
          LEFT JOIN `personal-information` pi ON pi.User_ID = u.User_ID
              WHERE u.Role = 'user'
           ORDER BY u.Created_At DESC, u.User_ID DESC
              LIMIT " . max(1, min(50, $limit))
        );
    }

    public static function registrationsByMonth(int $months = 6): array
    {
        $rows = Database::all(
            "SELECT DATE_FORMAT(Created_At, '%Y-%m') AS ym, COUNT(*) AS c FROM ems_users
              WHERE Role = 'user' AND Created_At >= DATE_SUB(DATE_FORMAT(CURDATE(), '%Y-%m-01'), INTERVAL ? MONTH)
           GROUP BY ym",
            [$months - 1]
        );
        $map = array_column($rows, 'c', 'ym');
        $out = [];
        $start = new \DateTimeImmutable('first day of this month');
        for ($i = $months - 1; $i >= 0; $i--) {
            $d = $start->modify("-$i month");
            $out[] = ['label' => $d->format('M Y'), 'count' => (int) ($map[$d->format('Y-m')] ?? 0)];
        }
        return $out;
    }
}
