<?php

declare(strict_types=1);

namespace PumpManager;

use PumpManager\Domain\PumpController;
use PumpManager\Domain\PumpControlService;
use PumpManager\Domain\PumpRepository;
use PumpManager\Http\JsonResponse;
use PumpManager\Http\Request;
use PumpManager\Http\Router;
use PumpManager\Support\AppException;
use PumpManager\Support\Config;
use PumpManager\TencentCloud\IotExplorerClient;
use Throwable;

final class App
{
    private readonly Router $router;
    private readonly Config $config;

    public function __construct(string $configPath)
    {
        $this->config = Config::fromFile($configPath);
        $repository = new PumpRepository($this->config->string('storage.pump_file'));
        $client = new IotExplorerClient(
            $this->config->string('tencent_cloud.secret_id'),
            $this->config->string('tencent_cloud.secret_key'),
            $this->config->string('tencent_cloud.region'),
            $this->config->string('tencent_cloud.endpoint'),
            $this->config->string('tencent_cloud.service'),
            $this->config->string('tencent_cloud.version')
        );
        $controller = new PumpController($repository, new PumpControlService($client, $this->config));

        $this->router = new Router();
        $this->routes($controller);
    }

    public function handle(Request $request): JsonResponse
    {
        try {
            $this->authenticate($request);
            $response = $this->router->dispatch($request);

            return $response instanceof JsonResponse ? $response : new JsonResponse(['data' => $response]);
        } catch (AppException $exception) {
            return $this->errorResponse($exception->getMessage(), $exception->statusCode(), $exception->details());
        } catch (Throwable $exception) {
            $details = $this->config->bool('app.debug') ? [
                'exception' => $exception::class,
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
            ] : [];

            return $this->errorResponse('Internal server error.', 500, $details);
        }
    }

    private function authenticate(Request $request): void
    {
        $expected = $this->config->string('app.api_token');
        if ($expected === '' || $request->path === '/api/health') {
            return;
        }

        $authorization = $request->headers['authorization'] ?? '';
        $bearerToken = str_starts_with($authorization, 'Bearer ') ? substr($authorization, 7) : '';
        $provided = $request->headers['x-api-token'] ?? $bearerToken;

        if (!hash_equals($expected, $provided)) {
            throw new AppException('Invalid or missing API token.', 401);
        }
    }

    private function routes(PumpController $controller): void
    {
        $this->router->get('/api/health', static fn (): JsonResponse => new JsonResponse([
            'status' => 'ok',
            'service' => 'tencent-iot-pump-manager',
        ]));

        $this->router->get('/api/pumps', static fn (): JsonResponse => $controller->index());
        $this->router->post('/api/pumps', static fn (Request $request): JsonResponse => $controller->store($request));
        $this->router->get('/api/pumps/{id}', static fn (Request $request, array $params): JsonResponse => $controller->show($params));
        $this->router->put('/api/pumps/{id}', static fn (Request $request, array $params): JsonResponse => $controller->update($request, $params));
        $this->router->delete('/api/pumps/{id}', static fn (Request $request, array $params): JsonResponse => $controller->destroy($params));
        $this->router->get('/api/pumps/{id}/data', static fn (Request $request, array $params): JsonResponse => $controller->deviceData($params));
        $this->router->post('/api/pumps/{id}/control', static fn (Request $request, array $params): JsonResponse => $controller->control($request, $params));
    }

    /**
     * @param array<string, mixed> $details
     */
    private function errorResponse(string $message, int $status, array $details = []): JsonResponse
    {
        $payload = [
            'error' => [
                'message' => $message,
                'status' => $status,
            ],
        ];

        if ($details !== []) {
            $payload['error']['details'] = $details;
        }

        return new JsonResponse($payload, $status);
    }
}
