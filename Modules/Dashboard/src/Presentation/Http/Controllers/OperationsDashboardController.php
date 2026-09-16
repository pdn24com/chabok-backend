<?php

declare(strict_types=1);

namespace Modules\Dashboard\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Dashboard\Application\UseCases\GetOperationsDashboard\GetOperationsDashboardHandler;
use Modules\Dashboard\Application\UseCases\GetOperationsDashboard\GetOperationsDashboardCommand;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class OperationsDashboardController
{
    public function __construct(private GetOperationsDashboardHandler $dashboard)
    {
    }

    public function __invoke(Request $request): JsonResponse
    {
        $nodeId = $request->attributes->get('node_id');
        if (!is_string($nodeId) || $nodeId === '') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'An active operational node is required.', ['X-Node-Id' => ['The operational node header is required.']]);
        }
        /** @var AuthenticatedPrincipal $principal */
        $principal = $request->attributes->get('principal');
        return ApiResponder::success($request, $this->dashboard->handle(new GetOperationsDashboardCommand($principal, $nodeId))->data);
    }
}
