<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Application\ApiResponder;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Operations\Application\OperationalDirectoryService;

final readonly class OperationalDirectoryController
{
    public function __construct(private OperationalDirectoryService $directory) {}

    public function drivers(Request $request): JsonResponse
    {
        $input = $request->validate(['capability' => ['sometimes', 'nullable', 'in:PICKUP,LINEHAUL,DELIVERY']]);
        return ApiResponder::success($request, $this->directory->drivers($request->attributes->get('principal'), $this->node($request), $input['capability'] ?? null));
    }

    public function vehicles(Request $request): JsonResponse
    {
        return ApiResponder::success($request, $this->directory->vehicles($request->attributes->get('principal'), $this->node($request)));
    }

    public function routes(Request $request): JsonResponse
    {
        return ApiResponder::success($request, $this->directory->routes($request->attributes->get('principal'), $this->node($request)));
    }

    private function node(Request $request): string
    {
        $nodeId = $request->attributes->get('node_id');
        if (! is_string($nodeId) || $nodeId === '') throw new ApiException(ApiErrorCode::ValidationError, 422, 'An active operational node is required.');
        return $nodeId;
    }
}
