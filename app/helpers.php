<?php
declare(strict_types=1);

use App\Core\Config;
use App\Core\Csrf;
use App\Core\Request;

/** HTML-escape a value for output. */
function e(mixed $value): string
{
    return htmlspecialchars((string) ($value ?? ''), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/** Base path of the application, e.g. "/doj-ems" or "" when served from the web root. */
function base_path(): string
{
    static $base = null;
    if ($base !== null) {
        return $base;
    }
    $configured = Config::get('app.base_url');
    if (is_string($configured)) {
        return $base = rtrim($configured, '/');
    }
    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $dir = rtrim(str_replace('\\', '/', dirname($script)), '/');
    if (str_ends_with($dir, '/public')) {
        $dir = substr($dir, 0, -7);
    }
    return $base = ($dir === '/' || $dir === '.') ? '' : $dir;
}

/** Build an application URL from a route path. */
function url(string $path = '/'): string
{
    return base_path() . '/' . ltrim($path, '/');
}

/** Build a URL to a static asset with a cache-busting version. */
function asset(string $path): string
{
    $file = APP_ROOT . '/public/assets/' . ltrim($path, '/');
    $v = is_file($file) ? substr(md5((string) filemtime($file)), 0, 8) : '0';
    return url('assets/' . ltrim($path, '/')) . '?v=' . $v;
}

function csrf_field(): string
{
    return '<input type="hidden" name="_csrf" value="' . e(Csrf::token()) . '">';
}

/** Encode data for a <script type="application/json"> block (safe against </script> injection). */
function json_for_html(mixed $data): string
{
    return json_encode($data, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
}

function client_ip(): string
{
    return Request::clientIp();
}

/** Human label for a learning & development lookup code. */
function lookup_label(string $list, mixed $code): string
{
    $map = Config::get('lookups.' . $list, []);
    return $map[(int) $code] ?? ('Code ' . (int) $code);
}
