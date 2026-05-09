<?php

declare(strict_types=1);

namespace PumpManager\Domain;

use PumpManager\Support\AppException;

final class PumpRepository
{
    public function __construct(private readonly string $file)
    {
        $directory = dirname($this->file);
        if (!is_dir($directory) && !mkdir($directory, 0775, true) && !is_dir($directory)) {
            throw new AppException("Unable to create storage directory: {$directory}", 500);
        }

        if (!is_file($this->file)) {
            file_put_contents($this->file, "[]\n", LOCK_EX);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function all(): array
    {
        return $this->read();
    }

    /**
     * @return array<string, mixed>
     */
    public function find(string $id): array
    {
        foreach ($this->read() as $pump) {
            if (($pump['id'] ?? '') === $id) {
                return $pump;
            }
        }

        throw new AppException('Pump not found.', 404, ['id' => $id]);
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function create(array $payload): array
    {
        $pumps = $this->read();
        $now = gmdate('c');
        $pump = $this->normalize($payload, null);
        $pump['created_at'] = $now;
        $pump['updated_at'] = $now;

        foreach ($pumps as $existing) {
            if (($existing['id'] ?? '') === $pump['id']) {
                throw new AppException('Pump id already exists.', 409, ['id' => $pump['id']]);
            }
        }

        $pumps[] = $pump;
        $this->write($pumps);

        return $pump;
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function update(string $id, array $payload): array
    {
        $pumps = $this->read();
        foreach ($pumps as $index => $pump) {
            if (($pump['id'] ?? '') !== $id) {
                continue;
            }

            $updated = $this->normalize($payload, $pump);
            $updated['id'] = $pump['id'];
            $updated['created_at'] = $pump['created_at'] ?? gmdate('c');
            $updated['updated_at'] = gmdate('c');
            $pumps[$index] = $updated;
            $this->write($pumps);

            return $updated;
        }

        throw new AppException('Pump not found.', 404, ['id' => $id]);
    }

    public function delete(string $id): void
    {
        $pumps = $this->read();
        $remaining = array_values(array_filter($pumps, static fn (array $pump): bool => ($pump['id'] ?? '') !== $id));

        if (count($remaining) === count($pumps)) {
            throw new AppException('Pump not found.', 404, ['id' => $id]);
        }

        $this->write($remaining);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array<string, mixed>|null $existing
     * @return array<string, mixed>
     */
    private function normalize(array $payload, ?array $existing): array
    {
        $id = $existing['id'] ?? $this->stringValue($payload, 'id', '');
        $name = $this->stringValue($payload, 'name', (string) ($existing['name'] ?? '顶峰永磁高速深井泵'));
        $productId = $this->stringValue($payload, 'product_id', (string) ($existing['product_id'] ?? ''));
        $deviceName = $this->stringValue($payload, 'device_name', (string) ($existing['device_name'] ?? ''));

        if ($productId === '' && isset($payload['productId'])) {
            $productId = (string) $payload['productId'];
        }

        if ($deviceName === '' && isset($payload['deviceName'])) {
            $deviceName = (string) $payload['deviceName'];
        }

        if ($id === '') {
            $id = strtolower(trim(preg_replace('/[^a-zA-Z0-9_-]+/', '-', "{$productId}-{$deviceName}") ?? '', '-'));
        }

        if (!preg_match('/^[a-zA-Z0-9_-]{2,64}$/', $id)) {
            throw new AppException('Pump id must contain 2-64 letters, numbers, underscores, or hyphens.', 422);
        }

        if ($productId === '' || $deviceName === '') {
            throw new AppException('product_id and device_name are required.', 422);
        }

        return [
            'id' => $id,
            'name' => $name,
            'product_id' => $productId,
            'device_name' => $deviceName,
            'model' => $this->stringValue($payload, 'model', (string) ($existing['model'] ?? 'Dingfeng PMSM High-Speed Deep-Well Pump')),
            'location' => $this->stringValue($payload, 'location', (string) ($existing['location'] ?? '')),
            'max_frequency_hz' => $this->intValue($payload, 'max_frequency_hz', (int) ($existing['max_frequency_hz'] ?? 400)),
            'notes' => $this->stringValue($payload, 'notes', (string) ($existing['notes'] ?? '')),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function read(): array
    {
        $json = file_get_contents($this->file);
        if ($json === false || trim($json) === '') {
            return [];
        }

        $decoded = json_decode($json, true);
        if (!is_array($decoded)) {
            throw new AppException('Pump storage file contains invalid JSON.', 500);
        }

        return array_values(array_filter($decoded, 'is_array'));
    }

    /**
     * @param array<int, array<string, mixed>> $pumps
     */
    private function write(array $pumps): void
    {
        $json = json_encode($pumps, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if ($json === false || file_put_contents($this->file, $json . "\n", LOCK_EX) === false) {
            throw new AppException('Unable to write pump storage file.', 500);
        }
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function stringValue(array $payload, string $key, string $default): string
    {
        $value = $payload[$key] ?? $default;

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function intValue(array $payload, string $key, int $default): int
    {
        $value = $payload[$key] ?? $default;

        return is_numeric($value) ? (int) $value : $default;
    }
}
