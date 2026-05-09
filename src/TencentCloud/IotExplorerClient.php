<?php

declare(strict_types=1);

namespace PumpManager\TencentCloud;

use PumpManager\Support\AppException;

final class IotExplorerClient
{
    public function __construct(
        private readonly string $secretId,
        private readonly string $secretKey,
        private readonly string $region,
        private readonly string $endpoint = 'iotexplorer.tencentcloudapi.com',
        private readonly string $service = 'iotexplorer',
        private readonly string $version = '2019-04-23'
    ) {
    }

    /**
     * @param array<string, mixed> $params
     * @return array<string, mixed>
     */
    public function request(string $action, array $params): array
    {
        if ($this->secretId === '' || $this->secretKey === '') {
            throw new AppException('Tencent Cloud credentials are not configured.', 500);
        }

        $payload = json_encode($params, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($payload === false) {
            throw new AppException('Unable to encode Tencent Cloud API payload.', 500);
        }

        $timestamp = time();
        $headers = $this->signedHeaders($action, $payload, $timestamp);
        $response = $this->postJson("https://{$this->endpoint}", $payload, $headers);

        $decoded = json_decode($response, true);
        if (!is_array($decoded) || !isset($decoded['Response']) || !is_array($decoded['Response'])) {
            throw new TencentCloudApiException('Unexpected Tencent Cloud API response.', 502, [
                'raw_response' => $response,
            ]);
        }

        $apiResponse = $decoded['Response'];
        if (isset($apiResponse['Error']) && is_array($apiResponse['Error'])) {
            $code = (string) ($apiResponse['Error']['Code'] ?? 'UnknownError');
            $message = (string) ($apiResponse['Error']['Message'] ?? 'Tencent Cloud API error.');

            throw new TencentCloudApiException($message, 502, [
                'code' => $code,
                'request_id' => $apiResponse['RequestId'] ?? null,
            ]);
        }

        return $apiResponse;
    }

    /**
     * @param array<string, mixed> $properties
     * @return array<string, mixed>
     */
    public function controlDeviceData(string $productId, string $deviceName, array $properties): array
    {
        $data = json_encode($properties, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($data === false) {
            throw new AppException('Unable to encode control properties.', 422);
        }

        return $this->request('ControlDeviceData', [
            'ProductId' => $productId,
            'DeviceName' => $deviceName,
            'Data' => $data,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function describeDeviceData(string $productId, string $deviceName): array
    {
        return $this->request('DescribeDeviceData', [
            'ProductId' => $productId,
            'DeviceName' => $deviceName,
        ]);
    }

    /**
     * @return array<int, string>
     */
    private function signedHeaders(string $action, string $payload, int $timestamp): array
    {
        $date = gmdate('Y-m-d', $timestamp);
        $canonicalHeaders = "content-type:application/json; charset=utf-8\n"
            . "host:{$this->endpoint}\n"
            . 'x-tc-action:' . strtolower($action) . "\n";
        $signedHeaders = 'content-type;host;x-tc-action';
        $canonicalRequest = "POST\n/\n\n{$canonicalHeaders}\n{$signedHeaders}\n" . hash('sha256', $payload);
        $credentialScope = "{$date}/{$this->service}/tc3_request";
        $stringToSign = "TC3-HMAC-SHA256\n{$timestamp}\n{$credentialScope}\n" . hash('sha256', $canonicalRequest);

        $secretDate = hash_hmac('sha256', $date, 'TC3' . $this->secretKey, true);
        $secretService = hash_hmac('sha256', $this->service, $secretDate, true);
        $secretSigning = hash_hmac('sha256', 'tc3_request', $secretService, true);
        $signature = hash_hmac('sha256', $stringToSign, $secretSigning);

        $authorization = 'TC3-HMAC-SHA256 '
            . "Credential={$this->secretId}/{$credentialScope}, "
            . "SignedHeaders={$signedHeaders}, "
            . "Signature={$signature}";

        return [
            'Authorization: ' . $authorization,
            'Content-Type: application/json; charset=utf-8',
            'Host: ' . $this->endpoint,
            'X-TC-Action: ' . $action,
            'X-TC-Timestamp: ' . (string) $timestamp,
            'X-TC-Version: ' . $this->version,
            'X-TC-Region: ' . $this->region,
        ];
    }

    /**
     * @param array<int, string> $headers
     */
    private function postJson(string $url, string $payload, array $headers): string
    {
        if (!function_exists('curl_init')) {
            throw new AppException('The PHP curl extension is required.', 500);
        }

        $handle = curl_init($url);
        if ($handle === false) {
            throw new AppException('Unable to initialize curl.', 500);
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $payload,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 30,
        ]);

        $response = curl_exec($handle);
        $error = curl_error($handle);
        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        curl_close($handle);

        if ($response === false) {
            throw new TencentCloudApiException('Tencent Cloud API request failed: ' . $error, 502);
        }

        if ($statusCode < 200 || $statusCode >= 300) {
            throw new TencentCloudApiException('Tencent Cloud API returned HTTP ' . $statusCode, 502, [
                'raw_response' => $response,
            ]);
        }

        return (string) $response;
    }
}
