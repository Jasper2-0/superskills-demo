<?php
declare(strict_types=1);

namespace App\Http;

use RuntimeException;

final class Router
{
    /** @var list<array{method: string, pattern: string, handler: callable}> */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void    { $this->routes[] = ['method' => 'GET',    'pattern' => $pattern, 'handler' => $handler]; }
    public function post(string $pattern, callable $handler): void   { $this->routes[] = ['method' => 'POST',   'pattern' => $pattern, 'handler' => $handler]; }
    public function put(string $pattern, callable $handler): void    { $this->routes[] = ['method' => 'PUT',    'pattern' => $pattern, 'handler' => $handler]; }
    public function delete(string $pattern, callable $handler): void { $this->routes[] = ['method' => 'DELETE', 'pattern' => $pattern, 'handler' => $handler]; }

    public function dispatch(string $method, string $path): mixed
    {
        $pathMatched = false;
        foreach ($this->routes as $route) {
            $regex = $this->compile($route['pattern']);
            if (preg_match($regex, $path, $m) === 1) {
                $pathMatched = true;
                if ($route['method'] === $method) {
                    array_shift($m); // drop the full match
                    $args = array_map(fn ($v) => ctype_digit($v) ? (int) $v : $v, $m);
                    return ($route['handler'])(...$args);
                }
            }
        }
        if ($pathMatched) {
            throw new RuntimeException('Method Not Allowed', 405);
        }
        throw new RuntimeException('Not Found', 404);
    }

    private function compile(string $pattern): string
    {
        // Replace {id} with integer-only capture
        $regex = preg_replace_callback('/\{(\w+)\}/', fn ($m) => $m[1] === 'id' ? '(\d+)' : '([^/]+)', $pattern);
        return '#^' . $regex . '$#';
    }
}
