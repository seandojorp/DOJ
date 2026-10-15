<?php
declare(strict_types=1);

/**
 * Application bootstrap: autoloader, configuration, error handling, timezone.
 */

define('APP_ROOT', dirname(__DIR__));
define('APP_PATH', APP_ROOT . '/app');
define('STORAGE_PATH', APP_ROOT . '/storage');

if (PHP_VERSION_ID < 80200) {
    http_response_code(500);
    exit('DOJ-NPS EMS requires PHP 8.2 or later.');
}

spl_autoload_register(static function (string $class): void {
    if (!str_starts_with($class, 'App\\')) {
        return;
    }
    $file = APP_PATH . '/' . str_replace('\\', '/', substr($class, 4)) . '.php';
    if (is_file($file)) {
        require $file;
    }
});

require APP_PATH . '/helpers.php';

$configFile = APP_ROOT . '/config/config.php';
if (!is_file($configFile)) {
    http_response_code(500);
    exit('Configuration missing: copy config/config.example.php to config/config.php and edit it (see INSTALL.md).');
}
App\Core\Config::load(require $configFile);

date_default_timezone_set((string) App\Core\Config::get('app.timezone', 'Asia/Manila'));

$isDev = App\Core\Config::get('app.env') === 'development';
error_reporting(E_ALL);
ini_set('display_errors', $isDev ? '1' : '0');
ini_set('log_errors', '1');
if (!is_dir(STORAGE_PATH . '/logs')) {
    @mkdir(STORAGE_PATH . '/logs', 0770, true);
}
ini_set('error_log', STORAGE_PATH . '/logs/php-error.log');

set_error_handler(static function (int $severity, string $message, string $file, int $line): bool {
    if (!(error_reporting() & $severity)) {
        return false;
    }
    if ($severity & (E_DEPRECATED | E_USER_DEPRECATED)) {
        error_log("Deprecated: $message in $file:$line");
        return true;
    }
    throw new ErrorException($message, 0, $severity, $file, $line);
});
