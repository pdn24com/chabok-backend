<?php

declare(strict_types=1);

namespace Modules\CrmTask\Presentation\Http\Resources;

use DateTimeImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\CrmTask\Domain\Enums\TaskStatus;
use Modules\CrmTask\Infrastructure\Persistence\Models\TaskRecord;

/**
 * One task wherever one is returned. The customer and the opportunity are named from the maps the
 * handler read through the ports, because their tables belong to other modules.
 *
 * @mixin TaskRecord
 */
final class TaskResource extends JsonResource
{
    /**
     * @param  array<string, string|null>  $customerNames
     * @param  array<string, string|null>  $opportunityTitles
     */
    public function __construct(
        TaskRecord $task,
        private readonly DateTimeImmutable $now,
        private readonly array $customerNames = [],
        private readonly array $opportunityTitles = [],
    ) {
        parent::__construct($task);
    }

    public function toArray(Request $request): array
    {
        return [
            'task_id' => $this->task_id,
            'title' => $this->title,
            'description' => $this->description,
            'status' => $this->status->value,
            'priority' => $this->priority->value,
            'due_at' => $this->due_at?->toISOString(),
            'remind_at' => $this->remind_at?->toISOString(),
            'completed_at' => $this->completed_at?->toISOString(),
            'completion_result' => $this->completion_result,
            'assignee' => $this->assignee === null ? null
                : ['user_id' => $this->assignee->user_id, 'display_name' => $this->assignee->display_name],
            'customer' => $this->named($this->customer_id, 'customer_id', 'display_name', $this->customerNames),
            'opportunity' => $this->named($this->opportunity_id, 'opportunity_id', 'title', $this->opportunityTitles),
            'is_overdue' => $this->isOverdue(),
            'created_at' => $this->created_at?->toISOString(),
        ];
    }

    /** Late means a deadline already past on a task nobody has finished or called off. */
    private function isOverdue(): bool
    {
        return $this->due_at !== null
            && $this->due_at < $this->now
            && ! in_array($this->status, [TaskStatus::COMPLETED, TaskStatus::CANCELLED], true);
    }

    /** @param array<string, string|null> $names */
    private function named(?string $id, string $key, string $label, array $names): ?array
    {
        return $id === null ? null : [$key => $id, $label => $names[$id] ?? null];
    }
}
