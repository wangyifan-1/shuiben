<?php

declare(strict_types=1);

namespace PumpManager\Http;

use PumpManager\Support\AppException;

final class Request
{
    /**
     * @param array<string, mixed> $query
     * @param array<string, string> $headers
     */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        private readonly string $rawBody,
        public readonly array $query,
        public readonly array $headers
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = self::normalizePath(parse_url($uri, PHP_URL_PATH) ?: '/');
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $rawBody = file_get_contents('php://input') ?: '';

        if ($method === 'POST') {
            $override = $_POST['_method'] ?? null;
            if (is_string($override) && in_array(strtoupper($override), ['PUT', 'PATCH', 'DELETE'], true)) {
                $method = strtoupper($override);
            }
        }

        return new self($method, $path, $rawBody, $_GET, self::headersFromServer());
    }

    private static function normalizePath(string $path): string
    {
        $route = $_GET['route'] ?? $_GET['_route'] ?? null;
        if (is_string($route) && $route !== '') {
            return '/' . ltrim($route, '/');
        }

        $scriptName = (string) ($_SERVER['SCRIPT_NAME'] ?? '');
        if ($scriptName !== '' && str_starts_with($path, $scriptName)) {
            $path = substr($path, strlen($scriptName)) ?: '/';
        }

        if (str_starts_with($path, '/index.php')) {
            $path = substr($path, strlen('/index.php')) ?: '/';
        }

        return '/' . ltrim($path, '/');
    }

    /**
     * @return array<string, mixed>
     */
    public function json(): array
    {
        if ($this->rawBody === '') {
            return [];
        }

        $decoded = json_decode($this->rawBody, true);
        if (!is_array($decoded)) {
            throw new AppException('Request body must be a valid JSON object.', 422);
        }

        return $decoded;
    }

    public function rawBody(): string
    {
        return $this->rawBody;
    }

    /**
     * @return array<string, string>
     */
    private static function headersFromServer(): array
    {
        $headers = [];
        foreach ($_SERVER as $key => $value) {
            if (!str_starts_with($key, 'HTTP_')) {
                continue;
            }

            $name = strtolower(str_replace('_', '-', substr($key, 5)));
            $headers[$name] = (string) $value;
        }

        return $headers;
    }
}
