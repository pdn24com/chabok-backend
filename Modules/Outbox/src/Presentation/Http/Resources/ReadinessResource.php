<?php

declare(strict_types=1);

namespace Modules\Outbox\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Outbox\Application\UseCases\CheckReadiness\CheckReadinessResult;

/** @mixin CheckReadinessResult */
final class ReadinessResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'ready' => $this->ready,
            'components' => [
                'mysql' => $this->mysql->value,
                'redis' => $this->redis->value,
                'outbox_worker' => $this->outboxWorker->value,
            ],
        ];
    }
}
