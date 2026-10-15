<?php
declare(strict_types=1);

namespace App\Core;

final class Response
{
    public static function securityHeaders(): void
    {
        header_remove('X-Powered-By');
        header('X-Content-Type-Options: nosniff');
        header('X-Frame-Options: DENY');
        header('Referrer-Policy: same-origin');
        header('Permissions-Policy: camera=(), microphone=(), geolocation=()');
        header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; "
            . "img-src 'self' data: blob:; font-src 'self'; connect-src 'self'; object-src 'none'; "
            . "frame-ancestors 'none'; form-action 'self'; base-uri 'self'");
        if (Request::isHttps()) {
            header('Strict-Transport-Security: max-age=31536000');
        }
    }

    public static function noStore(): void
    {
        header('Cache-Control: no-store, no-cache, must-revalidate, private');
        header('Pragma: no-cache');
    }

    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        self::noStore();
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE | JSON_PARTIAL_OUTPUT_ON_ERROR);
        exit;
    }

    public static function ok(mixed $data = null, string $message = '', int $status = 200): never
    {
        $payload = ['ok' => true];
        if ($message !== '') {
            $payload['message'] = $message;
        }
        if ($data !== null) {
            $payload['data'] = $data;
        }
        self::json($payload, $status);
    }

    public static function fail(string $message, int $status = 400, array $errors = []): never
    {
        $payload = ['ok' => false, 'message' => $message];
        if ($errors) {
            $payload['errors'] = $errors;
        }
        self::json($payload, $status);
    }

    public static function redirect(string $path, int $status = 302): never
    {
        $target = preg_match('#^https?://#i', $path) ? $path : url($path);
        header('Location: ' . $target, true, $status);
        exit;
    }
}
