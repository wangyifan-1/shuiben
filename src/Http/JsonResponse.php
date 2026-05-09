<?php

declare(strict_types=1);

namespace PumpManager\Http;

final class JsonResponse
{
    /**
     * @param array<string, mixed> $headers
     */
    public function __construct(
        private readonly mixed $data,
        private readonly int $status = 200,
        private readonly array $headers = []
    ) {
    }

    public function send(): void
    {
        http_response_code($this->status);
        header('Content-Type: application/json; charset=utf-8');
        foreach ($this->headers as $name => $value) {
            header("{$name}: {$value}");
        }

        echo json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    }
}
