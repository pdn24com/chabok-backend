<?php

declare(strict_types=1);

namespace Modules\Outbox\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Outbox\Application\UseCases\CheckReadiness\CheckReadinessCommand;
use Modules\Outbox\Application\UseCases\CheckReadiness\CheckReadinessHandler;
use Modules\Outbox\Presentation\Http\Resources\ReadinessResource;

final class OperationalHealthController
{
    public function live(): JsonResponse
    {
        return response()->json(['status' => 'UP']);
    }

    public function ready(Request $request, CheckReadinessHandler $checkReadinessHandler): JsonResponse
    {
        $result = $checkReadinessHandler->handle(new CheckReadinessCommand);

        return response()->json((new ReadinessResource($result))->resolve($request), $result->ready ? 200 : 503);
    }
}
