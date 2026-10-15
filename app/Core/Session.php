<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Hardened PHP session handling: strict mode, HttpOnly + SameSite cookies, Secure over HTTPS,
 * idle and absolute timeouts, and ID regeneration on privilege changes.
 */
final class Session
{
    public static function start(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            return;
        }
        $secureSetting = Config::get('session.secure', 'auto');
        $secure = $secureSetting === 'auto' ? Request::isHttps() : (bool) $secureSetting;

        ini_set('session.use_strict_mode', '1');
        ini_set('session.use_only_cookies', '1');
        ini_set('session.use_trans_sid', '0');
        ini_set('session.cookie_httponly', '1');
        if (PHP_VERSION_ID < 80400) { // these settings are deprecated (and fixed to strong defaults) in PHP 8.4
            ini_set('session.sid_length', '48');
            ini_set('session.sid_bits_per_character', '6');
        }
        ini_set('session.gc_maxlifetime', (string) max(1800, (int) Config::get('session.absolute_timeout', 28800)));

        if (Config::get('session.handler', 'files') === 'db') {
            // Garbage-collect expired rows on ~1% of requests.
            ini_set('session.gc_probability', '1');
            ini_set('session.gc_divisor', '100');
            session_set_save_handler(new DatabaseSessionHandler(), true);
        }

        session_name((string) Config::get('session.name', 'DOJEMS_SID'));
        session_set_cookie_params([
            'lifetime' => 0,
            'path'     => base_path() === '' ? '/' : base_path() . '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_start();

        $now = time();
        $idle = (int) Config::get('session.idle_timeout', 1800);
        $absolute = (int) Config::get('session.absolute_timeout', 28800);

        if (isset($_SESSION['uid'])) {
            $last = (int) ($_SESSION['last_activity'] ?? 0);
            $started = (int) ($_SESSION['login_at'] ?? 0);
            if (($idle > 0 && $now - $last > $idle) || ($absolute > 0 && $now - $started > $absolute)) {
                self::destroy();
                session_start();
                $_SESSION['flash_expired'] = true;
            }
        }
        $_SESSION['last_activity'] = $now;
    }

    public static function regenerate(): void
    {
        if (session_status() === PHP_SESSION_ACTIVE) {
            session_regenerate_id(true);
        }
    }

    public static function destroy(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return;
        }
        $_SESSION = [];
        $p = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $p['path'],
            'secure'   => $p['secure'],
            'httponly' => true,
            'samesite' => 'Strict',
        ]);
        session_destroy();
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        return $_SESSION[$key] ?? $default;
    }

    public static function set(string $key, mixed $value): void
    {
        $_SESSION[$key] = $value;
    }

    /** Read and remove a one-time value. */
    public static function pull(string $key, mixed $default = null): mixed
    {
        $v = $_SESSION[$key] ?? $default;
        unset($_SESSION[$key]);
        return $v;
    }
}
