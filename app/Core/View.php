<?php
declare(strict_types=1);

namespace App\Core;

/** Minimal PHP template renderer with layouts. Templates must escape output with e(). */
final class View
{
    private static array $shared = [];

    /** Templates call this to pass values (page scripts, flags) up to the layout. */
    public static function share(string $key, mixed $value): void
    {
        self::$shared[$key] = $value;
    }

    public static function render(string $template, array $data = [], ?string $layout = 'app'): never
    {
        Response::noStore();
        header('Content-Type: text/html; charset=utf-8');
        $content = self::capture($template, $data);
        if ($layout !== null) {
            echo self::capture('layouts/' . $layout, ['content' => $content] + self::$shared + $data);
        } else {
            echo $content;
        }
        exit;
    }

    public static function capture(string $template, array $data = []): string
    {
        $file = APP_PATH . '/Views/' . $template . '.php';
        if (!is_file($file)) {
            throw new \RuntimeException('View not found: ' . $template);
        }
        extract($data, EXTR_SKIP);
        ob_start();
        try {
            include $file;
        } catch (\Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        return (string) ob_get_clean();
    }
}
