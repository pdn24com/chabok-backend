<?php

declare(strict_types=1);

namespace Modules\Outbox\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Modules\Outbox\Application\UseCases\CheckReadiness\CheckReadinessHandler;
use Modules\Outbox\Application\UseCases\CheckReadiness\CheckReadinessCommand;

final readonly class OperationalHealthController
{
    public function __construct(private CheckReadinessHandler $health)
    {
    }

    public function live(): JsonResponse
    {
        return response()->json(['status' => 'UP']);
    }

    public function ready(): JsonResponse
    {
        $result = $this->health->handle(new CheckReadinessCommand())->data;
        return response()->json($result, $result['ready'] ? 200 : 503);
    }
}
