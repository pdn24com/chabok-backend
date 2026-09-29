<?php

declare(strict_types=1);

namespace Modules\Dashboard\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Dashboard\Application\UseCases\GetOperationsDashboard\GetOperationsDashboardCommand;
use Modules\Dashboard\Application\UseCases\GetOperationsDashboard\GetOperationsDashboardHandler;
use Modules\Dashboard\Presentation\Http\Resources\OperationsDashboardResource;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Foundation\Presentation\Http\ApiResponder;

final class OperationsDashboardController
{
    public function __invoke(Request $request, GetOperationsDashboardHandler $getOperationsDashboardHandler): JsonResponse
    {
        $nodeId = $request->attributes->get('node_id');
        if (! is_string($nodeId) || $nodeId === '') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'common.active_operational_node_is_required', ['X-Node-Id' => ['common.operational_node_header_is_required']]);
        }
        /** @var AuthenticatedPrincipal $principal */
        $principal = $request->attributes->get('principal');

        return ApiResponder::success($request, (new OperationsDashboardResource($getOperationsDashboardHandler->handle(new GetOperationsDashboardCommand($principal, $nodeId))))->resolve($request));
    }
}
