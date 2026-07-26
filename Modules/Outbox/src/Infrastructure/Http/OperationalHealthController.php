<?php

declare(strict_types=1);

namespace Modules\Outbox\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Modules\Outbox\Application\OutboxHealthService;

final readonly class OperationalHealthController
{
    public function __construct(private OutboxHealthService $health) {}

    public function live(): JsonResponse
    {
        return response()->json(['status' => 'UP']);
    }

    public function ready(): JsonResponse
    {
        $result = $this->health->readiness();

        return response()->json($result, $result['ready'] ? 200 : 503);
    }
}
