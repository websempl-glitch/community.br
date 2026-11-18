<?php
declare(strict_types=1);

namespace App;

class Router
{
    private array $routes = ['GET' => [], 'POST' => []];

    public function get(string $path, array $handler): void
    {
        $this->routes['GET'][] = ['pattern' => $path, 'handler' => $handler];
    }

    public function post(string $path, array $handler): void
    {
        $this->routes['POST'][] = ['pattern' => $path, 'handler' => $handler];
    }

    public function dispatch(string $method, string $path): void
    {
        $path = rtrim($path, '/') ?: '/';

        foreach ($this->routes[$method] ?? [] as $route) {
            $params = $this->match($route['pattern'], $path);
            if ($params !== null) {
                [$class, $action] = $route['handler'];
                (new $class())->$action(...array_values($params));
                return;
            }
        }

        http_response_code(404);
        view('errors/404', ['pageTitle' => '404']);
    }

    /**
     * Совпадение с паттерном вида /article/{slug}
     * @return array<string,string>|null
     */
    private function match(string $pattern, string $path): ?array
    {
        if ($pattern === $path) return [];

        $regex = preg_replace('#\{([a-z_]+)\}#', '(?P<$1>[^/]+)', $pattern);
        $regex = '#^' . $regex . '$#';

        if (preg_match($regex, $path, $m)) {
            return array_filter($m, 'is_string', ARRAY_FILTER_USE_KEY);
        }
        return null;
    }
}