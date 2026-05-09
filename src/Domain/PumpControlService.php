<?php

declare(strict_types=1);

namespace PumpManager\Domain;

use PumpManager\Support\AppException;
use PumpManager\Support\Config;
use PumpManager\TencentCloud\IotExplorerClient;

final class PumpControlService
{
    public function __construct(
        private readonly IotExplorerClient $client,
        private readonly Config $config
    ) {
    }

    /**
     * @param array<string, mixed> $pump
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function control(array $pump, array $payload): array
    {
        $command = strtolower(trim((string) ($payload['command'] ?? '')));
        if ($command === '') {
            throw new AppException('command is required.', 422);
        }

        $properties = match ($command) {
            'start' => $this->startProperties(),
            'stop' => $this->stopProperties(),
            'set_frequency' => $this->frequencyProperties($pump, $payload),
            'set_mode' => $this->modeProperties($payload),
            'reset_fault' => $this->resetFaultProperties(),
            'raw' => $this->rawProperties($payload),
            default => throw new AppException('Unsupported pump command.', 422, [
                'supported_commands' => ['start', 'stop', 'set_frequency', 'set_mode', 'reset_fault', 'raw'],
            ]),
        };

        $response = $this->client->controlDeviceData(
            (string) $pump['product_id'],
            (string) $pump['device_name'],
            $properties
        );

        return [
            'pump_id' => $pump['id'],
            'command' => $command,
            'properties' => $properties,
            'cloud_response' => $response,
        ];
    }

    /**
     * @param array<string, mixed> $pump
     * @return array<string, mixed>
     */
    public function data(array $pump): array
    {
        return $this->client->describeDeviceData(
            (string) $pump['product_id'],
            (string) $pump['device_name']
        );
    }

    /**
     * @return array<string, int>
     */
    private function startProperties(): array
    {
        return [$this->identifier('power_switch') => 1];
    }

    /**
     * @return array<string, int>
     */
    private function stopProperties(): array
    {
        return [$this->identifier('power_switch') => 0];
    }

    /**
     * @param array<string, mixed> $pump
     * @param array<string, mixed> $payload
     * @return array<string, int|float>
     */
    private function frequencyProperties(array $pump, array $payload): array
    {
        if (!isset($payload['frequency_hz']) || !is_numeric($payload['frequency_hz'])) {
            throw new AppException('frequency_hz is required for set_frequency.', 422);
        }

        $frequency = (float) $payload['frequency_hz'];
        $maxFrequency = max(1, (float) ($pump['max_frequency_hz'] ?? $this->config->int('pump_defaults.max_frequency_hz', 400)));
        if ($frequency < 0 || $frequency > $maxFrequency) {
            throw new AppException("frequency_hz must be between 0 and {$maxFrequency}.", 422);
        }

        $value = fmod($frequency, 1.0) === 0.0 ? (int) $frequency : $frequency;

        return [$this->identifier('target_frequency') => $value];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, string>
     */
    private function modeProperties(array $payload): array
    {
        $mode = trim((string) ($payload['mode'] ?? ''));
        if ($mode === '') {
            throw new AppException('mode is required for set_mode.', 422);
        }

        return [$this->identifier('work_mode') => $mode];
    }

    /**
     * @return array<string, int>
     */
    private function resetFaultProperties(): array
    {
        return [$this->identifier('fault_reset') => 1];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function rawProperties(array $payload): array
    {
        $properties = $payload['properties'] ?? null;
        if (!is_array($properties) || $properties === []) {
            throw new AppException('properties must be a non-empty object for raw control.', 422);
        }

        return $properties;
    }

    private function identifier(string $key): string
    {
        $identifier = $this->config->string("thing_model.{$key}");
        if ($identifier === '') {
            throw new AppException("Thing model identifier is missing: {$key}", 500);
        }

        return $identifier;
    }
}
