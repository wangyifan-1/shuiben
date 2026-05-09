<?php

declare(strict_types=1);

namespace PumpManager\Http;

use PumpManager\Support\AppException;

final class Router
{
    /**
     * @var array<int, array{method: string, pattern: string, handler: callable}>
     */
    private array $routes = [];

    public function get(string $pattern, callable $handler): void
    {
        $this->add('GET', $pattern, $handler);
    }

    public function post(string $pattern, callable $handler): void
    {
        $this->add('POST', $pattern, $handler);
    }

    public function put(string $pattern, callable $handler): void
    {
        $this->add('PUT', $pattern, $handler);
    }

    public function delete(string $pattern, callable $handler): void
    {
        $this->add('DELETE', $pattern, $handler);
    }

    public function add(string $method, string $pattern, callable $handler): void
    {
        $this->routes[] = [
            'method' => strtoupper($method),
            'pattern' => $pattern,
            'handler' => $handler,
        ];
    }

    public function dispatch(Request $request): mixed
    {
        foreach ($this->routes as $route) {
            if ($route['method'] !== $request->method) {
                continue;
            }

            $params = $this->match($route['pattern'], $request->path);
            if ($params === null) {
                continue;
            }

            return ($route['handler'])($request, $params);
        }

        throw new AppException('Route not found.', 404, [
            'method' => $request->method,
            'path' => $request->path,
        ]);
    }

    /**
     * @return array<string, string>|null
     */
    private function match(string $pattern, string $path): ?array
    {
        $paramNames = [];
        $regex = preg_replace_callback('/\{([a-zA-Z_][a-zA-Z0-9_]*)}/', function (array $matches) use (&$paramNames): string {
            $paramNames[] = $matches[1];

            return '([^/]+)';
        }, $pattern);

        if ($regex === null) {
            return null;
        }

        if (!preg_match('#^' . $regex . '$#', $path, $matches)) {
            return null;
        }

        array_shift($matches);
        $params = [];
        foreach ($paramNames as $index => $name) {
            $params[$name] = rawurldecode($matches[$index] ?? '');
        }

        return $params;
    }
}
