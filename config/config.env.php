<?php
/**
 * Configuration built from environment variables — used automatically when config/config.php
 * does not exist (e.g. on Vercel, where secrets live in Project Settings → Environment Variables).
 *
 * Starts from config.example.php and overrides values with these variables when set:
 *   APP_ENV, APP_KEY, APP_BASE_URL, APP_TIMEZONE
 *   DB_HOST, DB_PORT, DB_NAME, DB_USER, DB_PASS, DB_TIME_ZONE
 *   DB_SSL (true/false), DB_SSL_CA (path to a CA bundle; auto-detected when DB_SSL=true)
 *   SESSION_HANDLER ('files' or 'db'), SESSION_SECURE ('auto', 'true', 'false')
 *   TRUST_PROXY (true/false)
 *
 * This file contains no secrets and is safe to commit.
 */
$config = require __DIR__ . '/config.example.php';

$env = static function (string $name): ?string {
    $v = getenv($name);
    return ($v === false || $v === '') ? null : $v;
};
$bool = static fn (?string $v, bool $default): bool => $v === null
    ? $default
    : in_array(strtolower($v), ['1', 'true', 'yes', 'on'], true);

$onVercel = getenv('VERCEL') !== false;

$config['app']['env']      = $env('APP_ENV') ?? $config['app']['env'];
$config['app']['key']      = $env('APP_KEY') ?? $config['app']['key'];
$config['app']['timezone'] = $env('APP_TIMEZONE') ?? $config['app']['timezone'];
// On Vercel the front controller runs as /api/index.php, so auto-detection would give "/api".
$baseUrl = getenv('APP_BASE_URL');
$config['app']['base_url'] = $baseUrl !== false ? $baseUrl : ($onVercel ? '' : $config['app']['base_url']);

$config['db']['host']      = $env('DB_HOST') ?? $config['db']['host'];
$config['db']['port']      = (int) ($env('DB_PORT') ?? $config['db']['port']);
$config['db']['name']      = $env('DB_NAME') ?? $config['db']['name'];
$config['db']['user']      = $env('DB_USER') ?? $config['db']['user'];
$config['db']['pass']      = getenv('DB_PASS') !== false ? (string) getenv('DB_PASS') : $config['db']['pass'];
$config['db']['time_zone'] = $env('DB_TIME_ZONE') ?? $config['db']['time_zone'];

if ($bool($env('DB_SSL'), false)) {
    $ca = $env('DB_SSL_CA');
    if ($ca === null) {
        foreach ([
            '/etc/pki/tls/certs/ca-bundle.crt',   // Amazon Linux (Vercel functions)
            '/etc/ssl/certs/ca-certificates.crt', // Debian / Ubuntu
            '/etc/ssl/cert.pem',                  // macOS / Alpine
        ] as $candidate) {
            if (is_file($candidate)) {
                $ca = $candidate;
                break;
            }
        }
    }
    $config['db']['ssl_ca'] = $ca;
}

// Serverless instances do not share a disk, so file sessions would log people out at random.
$config['session']['handler'] = $env('SESSION_HANDLER') ?? ($onVercel ? 'db' : 'files');
$secure = $env('SESSION_SECURE');
if ($secure !== null) {
    $config['session']['secure'] = strtolower($secure) === 'auto' ? 'auto' : $bool($secure, false);
}

// Vercel terminates HTTPS and sets X-Forwarded-For / X-Forwarded-Proto itself.
$config['security']['trust_proxy'] = $bool($env('TRUST_PROXY'), $onVercel);

return $config;
