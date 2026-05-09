<?php

declare(strict_types=1);

namespace PumpManager\Domain;

use PumpManager\Http\JsonResponse;
use PumpManager\Http\Request;

final class PumpController
{
    public function __construct(
        private readonly PumpRepository $repository,
        private readonly PumpControlService $controlService
    ) {
    }

    public function index(): JsonResponse
    {
        return new JsonResponse([
            'data' => $this->repository->all(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        return new JsonResponse([
            'data' => $this->repository->create($request->json()),
        ], 201);
    }

    /**
     * @param array<string, string> $params
     */
    public function show(array $params): JsonResponse
    {
        return new JsonResponse([
            'data' => $this->repository->find($params['id']),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function update(Request $request, array $params): JsonResponse
    {
        return new JsonResponse([
            'data' => $this->repository->update($params['id'], $request->json()),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function destroy(array $params): JsonResponse
    {
        $this->repository->delete($params['id']);

        return new JsonResponse([
            'deleted' => true,
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function deviceData(array $params): JsonResponse
    {
        $pump = $this->repository->find($params['id']);

        return new JsonResponse([
            'data' => $this->controlService->data($pump),
        ]);
    }

    /**
     * @param array<string, string> $params
     */
    public function control(Request $request, array $params): JsonResponse
    {
        $pump = $this->repository->find($params['id']);

        return new JsonResponse([
            'data' => $this->controlService->control($pump, $request->json()),
        ]);
    }
}
