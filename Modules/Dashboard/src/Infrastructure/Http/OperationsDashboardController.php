<?php

declare(strict_types=1);

namespace Modules\Dashboard\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Dashboard\Application\OperationsDashboardQuery;
use Modules\Foundation\Application\ApiResponder;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class OperationsDashboardController
{
    public function __construct(private OperationsDashboardQuery $dashboard) {}

    public function __invoke(Request $request): JsonResponse
    {
        $nodeId = $request->attributes->get('node_id');
        if (! is_string($nodeId) || $nodeId === '') {
            throw new ApiException(
                ApiErrorCode::ValidationError,
                422,
                'An active operational node is required.',
                ['X-Node-Id' => ['The operational node header is required.']],
            );
        }

        /** @var AuthenticatedPrincipal $principal */
        $principal = $request->attributes->get('principal');

        return ApiResponder::success(
            $request,
            $this->dashboard->get($principal, $nodeId),
        );
    }
}
