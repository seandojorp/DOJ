<?php
declare(strict_types=1);

namespace App\Core;

/**
 * Route table with access guards. Each route declares who may call it:
 *   'guest'  – anyone (setup/login/register pages)
 *   'auth'   – any signed-in, active account
 *   'admin'  – signed-in administrators only
 *   'user'   – signed-in regular users only
 * Guards are enforced here on the server for both pages and API endpoints.
 */
final class Router
{
    private array $routes = [];

    public function add(string $method, string $pattern, array $handler, string $access = 'auth'): void
    {
        $regex = '#^' . preg_replace_callback('/\{(\w+)(?::([^}]+))?\}/', static function (array $m): string {
            $re = $m[2] ?? ($m[1] === 'id' ? '\d+' : '[a-z0-9_-]+');
            return '(?P<' . $m[1] . '>' . $re . ')';
        }, $pattern) . '$#';
        $this->routes[] = [strtoupper($method), $regex, $handler, $access];
    }

    public function get(string $p, array $h, string $a = 'auth'): void    { $this->add('GET', $p, $h, $a); }
    public function post(string $p, array $h, string $a = 'auth'): void   { $this->add('POST', $p, $h, $a); }
    public function put(string $p, array $h, string $a = 'auth'): void    { $this->add('PUT', $p, $h, $a); }
    public function delete(string $p, array $h, string $a = 'auth'): void { $this->add('DELETE', $p, $h, $a); }

    public function dispatch(string $method, string $path): void
    {
        $allowed = [];
        foreach ($this->routes as [$m, $regex, $handler, $access]) {
            if (!preg_match($regex, $path, $matches)) {
                continue;
            }
            if ($m !== $method) {
                $allowed[] = $m;
                continue;
            }
            $params = array_filter($matches, 'is_string', ARRAY_FILTER_USE_KEY);

            if ($method !== 'GET' && $method !== 'HEAD') {
                Csrf::verifyRequest();
            }
            $this->guard($access);

            [$class, $action] = $handler;
            (new $class())->$action(...$params);
            return;
        }
        if ($allowed) {
            header('Allow: ' . implode(', ', array_unique($allowed)));
            throw new HttpException(405, 'Method not allowed.');
        }
        throw new HttpException(404, 'The page you requested was not found.');
    }

    private function guard(string $access): void
    {
        if ($access === 'guest') {
            return;
        }
        $user = Auth::user();
        if ($user === null) {
            if (Request::isApi()) {
                throw new HttpException(401, 'Your session has ended. Please sign in again.');
            }
            Session::set('intended', Request::path());
            Response::redirect('/login');
        }
        if (($access === 'admin' && $user['Role'] !== 'admin') || ($access === 'user' && $user['Role'] !== 'user')) {
            Audit::log('ACCESS_DENIED', null, Request::path(), 'failure');
            throw new HttpException(403, 'You do not have permission to access this resource.');
        }
    }
}
