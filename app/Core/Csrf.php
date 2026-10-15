<?php
declare(strict_types=1);

namespace App\Core;

/** Synchronizer-token CSRF protection for every state-changing request. */
final class Csrf
{
    public static function token(): string
    {
        $t = Session::get('_csrf');
        if (!is_string($t) || strlen($t) !== 64) {
            $t = bin2hex(random_bytes(32));
            Session::set('_csrf', $t);
        }
        return $t;
    }

    public static function rotate(): void
    {
        Session::set('_csrf', bin2hex(random_bytes(32)));
    }

    public static function verifyRequest(): void
    {
        $sent = Request::header('X-CSRF-Token') ?? (is_string($_POST['_csrf'] ?? null) ? $_POST['_csrf'] : null);
        if ($sent === null) {
            $in = Request::input('_csrf');
            $sent = is_string($in) ? $in : null;
        }
        $expected = Session::get('_csrf');
        if (!is_string($sent) || !is_string($expected) || !hash_equals($expected, $sent)) {
            throw new HttpException(419, 'Your session has expired or the request could not be verified. Please reload the page and try again.');
        }
    }
}
