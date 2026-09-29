<?php

declare(strict_types=1);

namespace Modules\Customer\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmTask\Infrastructure\Persistence\Models\TaskRecord;

/** @mixin TaskRecord */
final class CustomerTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'task_id' => $this->task_id,
            'title' => $this->title,
            'status' => $this->status->value,
            'due_at' => $this->due_at?->toISOString(),
        ];
    }
}
