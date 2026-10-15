<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Session-based authentication.
 *
 * The session stores only the User_ID and a keyed fingerprint of the account's current password hash.
 * On every request the account is re-read from the database, so:
 *   - deactivated accounts are signed out immediately;
 *   - role changes take effect immediately;
 *   - a password change or admin reset invalidates every other session for that account
 *     (their fingerprint no longer matches) without needing an extra database column.
 */
final class Auth
{
    private static array|false|null $user = null;

    public const PASSWORD_REGEX = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{10,}$/';
    public const PASSWORD_MAX_BYTES = 72; // bcrypt input limit

    public static function user(): ?array
    {
        if (self::$user !== null) {
            return self::$user ?: null;
        }
        $uid = Session::get('uid');
        if (!is_int($uid)) {
            self::$user = false;
            return null;
        }
        $row = Database::one(
            'SELECT u.User_ID, u.Preload_Users_ID, u.UserName, u.password_hash, u.Role, u.Account_Status, u.Last_Login,
                    p.DOJ_ID, p.Cellphone_No, p.Email_Address
               FROM ems_users u
          LEFT JOIN preload_users p ON p.Preload_Users_ID = u.Preload_Users_ID
              WHERE u.User_ID = ?',
            [$uid]
        );
        if ($row === null
            || $row['Account_Status'] !== 'active'
            || !hash_equals((string) Session::get('pwfp', ''), self::fingerprint($row['password_hash']))) {
            Session::destroy();
            Session::start();
            Session::set('flash_expired', true);
            self::$user = false;
            return null;
        }
        unset($row['password_hash']);
        $row['User_ID'] = (int) $row['User_ID'];
        return self::$user = $row;
    }

    public static function id(): ?int
    {
        return self::user()['User_ID'] ?? null;
    }

    public static function isAdmin(): bool
    {
        return (self::user()['Role'] ?? null) === 'admin';
    }

    /** Establish an authenticated session for a verified account row (must include password_hash). */
    public static function login(array $account): void
    {
        Session::regenerate();              // defeat session fixation
        $_SESSION = array_intersect_key($_SESSION, ['_csrf' => 1]);
        Session::set('uid', (int) $account['User_ID']);
        Session::set('pwfp', self::fingerprint($account['password_hash']));
        Session::set('login_at', time());
        Session::set('last_activity', time());
        Csrf::rotate();
        self::$user = null;
    }

    /** Refresh the current session after the signed-in user changes their own password. */
    public static function refreshFingerprint(string $newHash): void
    {
        Session::regenerate();
        Session::set('pwfp', self::fingerprint($newHash));
        self::$user = null;
    }

    public static function logout(): void
    {
        Session::destroy();
        self::$user = false;
    }

    public static function fingerprint(string $passwordHash): string
    {
        return hash_hmac('sha256', $passwordHash, (string) Config::get('app.key', ''));
    }

    public static function hash(string $password): string
    {
        return password_hash($password, PASSWORD_DEFAULT);
    }

    /** Returns a list of unmet password rules (empty list = valid). */
    public static function passwordProblems(string $password): array
    {
        $problems = [];
        if (mb_strlen($password) < 10) $problems[] = 'at least 10 characters';
        if (!preg_match('/[A-Z]/', $password)) $problems[] = 'an uppercase letter';
        if (!preg_match('/[a-z]/', $password)) $problems[] = 'a lowercase letter';
        if (!preg_match('/\d/', $password)) $problems[] = 'a number';
        if (!preg_match('/[^A-Za-z0-9]/', $password)) $problems[] = 'a special character';
        if (strlen($password) > self::PASSWORD_MAX_BYTES) $problems[] = 'no more than 72 characters';
        if ($problems === [] && !preg_match(self::PASSWORD_REGEX, $password)) $problems[] = 'a valid combination of characters';
        return $problems;
    }

    /**
     * Validate a new password + confirmation pair and return field errors.
     */
    public static function validateNewPassword(string $password, string $confirm, string $field = 'password', string $confirmField = 'confirm_password'): array
    {
        $errors = [];
        $problems = self::passwordProblems($password);
        if ($problems) {
            $errors[$field] = 'Password must contain ' . implode(', ', $problems) . '.';
        }
        if ($password !== $confirm) {
            $errors[$confirmField] = 'Passwords do not match.';
        }
        return $errors;
    }
}
