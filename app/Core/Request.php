<?php
declare(strict_types=1);

namespace App\Core;

final class Request
{
    private static ?array $body = null;

    public static function method(): string
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
    }

    /** Route path relative to the application base, always starting with "/". */
    public static function path(): string
    {
        $uri = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
        $uri = rawurldecode($uri);
        $base = base_path();
        if ($base !== '' && str_starts_with($uri, $base)) {
            $uri = substr($uri, strlen($base));
        }
        if (str_starts_with($uri, '/public/') || $uri === '/public') {
            $uri = substr($uri, 7);
        }
        if ($uri === '/index.php' || str_starts_with($uri, '/index.php/')) {
            $uri = substr($uri, 10);
        }
        $uri = '/' . trim($uri, '/');
        return $uri;
    }

    public static function isApi(): bool
    {
        return str_starts_with(self::path(), '/api/');
    }

    /** Merged request input: JSON body, form body, then query string (body wins). */
    public static function all(): array
    {
        if (self::$body === null) {
            $body = [];
            $type = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));
            if (str_contains($type, 'application/json')) {
                $raw = file_get_contents('php://input', false, null, 0, 2 * 1024 * 1024);
                $decoded = json_decode((string) $raw, true);
                $body = is_array($decoded) ? $decoded : [];
            } elseif (self::method() === 'POST') {
                $body = $_POST;
            } elseif (in_array(self::method(), ['PUT', 'PATCH', 'DELETE'], true)) {
                parse_str((string) file_get_contents('php://input'), $body);
            }
            self::$body = $body + $_GET;
        }
        return self::$body;
    }

    public static function input(string $key, mixed $default = null): mixed
    {
        $all = self::all();
        return array_key_exists($key, $all) ? $all[$key] : $default;
    }

    /** Input as a trimmed string ('' if missing or not scalar). */
    public static function str(string $key): string
    {
        $v = self::input($key);
        return is_scalar($v) ? trim((string) $v) : '';
    }

    /** Raw (untrimmed) string, for passwords. */
    public static function raw(string $key): string
    {
        $v = self::input($key);
        return is_scalar($v) ? (string) $v : '';
    }

    public static function query(string $key, mixed $default = null): mixed
    {
        return $_GET[$key] ?? $default;
    }

    public static function header(string $name): ?string
    {
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return isset($_SERVER[$key]) ? (string) $_SERVER[$key] : null;
    }

    public static function file(string $key): ?array
    {
        $f = $_FILES[$key] ?? null;
        return is_array($f) && isset($f['error']) && !is_array($f['error']) ? $f : null;
    }

    public static function isHttps(): bool
    {
        if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
            return true;
        }
        if (Config::get('security.trust_proxy') && strtolower((string) (self::header('X-Forwarded-Proto') ?? '')) === 'https') {
            return true;
        }
        return false;
    }

    public static function clientIp(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if (Config::get('security.trust_proxy')) {
            $fwd = self::header('X-Forwarded-For');
            if ($fwd) {
                $first = trim(explode(',', $fwd)[0]);
                if (filter_var($first, FILTER_VALIDATE_IP)) {
                    $ip = $first;
                }
            }
        }
        return filter_var($ip, FILTER_VALIDATE_IP) ? substr($ip, 0, 45) : '0.0.0.0';
    }
}
