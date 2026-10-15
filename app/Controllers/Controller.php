<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Auth;
use App\Core\HttpException;
use App\Core\View;

abstract class Controller
{
    /** Render a page inside the signed-in application layout. */
    protected function render(string $template, string $title, array $data = [], string $active = ''): never
    {
        View::render($template, $data + [
            'title'   => $title,
            'active'  => $active,
            'me'      => Auth::user(),
        ], 'app');
    }

    protected function intId(mixed $v): int
    {
        if (!is_numeric($v) || (int) $v < 1) {
            throw new HttpException(400, 'Invalid identifier.');
        }
        return (int) $v;
    }
}
