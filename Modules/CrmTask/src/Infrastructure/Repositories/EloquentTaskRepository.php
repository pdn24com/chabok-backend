<?php

declare(strict_types=1);

namespace Modules\CrmTask\Infrastructure\Repositories;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Modules\CrmTask\Application\Dto\TaskListFiltersDto;
use Modules\CrmTask\Application\Dto\TaskSummaryDto;
use Modules\CrmTask\Application\Repositories\TaskRepositoryInterface;
use Modules\CrmTask\Domain\Enums\TaskBucket;
use Modules\CrmTask\Domain\Enums\TaskStatus;
use Modules\CrmTask\Infrastructure\Persistence\Models\TaskRecord;

final class EloquentTaskRepository implements TaskRepositoryInterface
{
    public function openForCustomer(string $hqId, string $customerId): Collection
    {
        return TaskRecord::query()
            ->where(['hq_id' => $hqId, 'customer_id' => $customerId])
            ->whereNotIn('status', TaskStatus::closedValues())
            // A task without a due date carries no deadline, so it sorts behind every dated one.
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->orderBy('id')
            ->get(['id', 'title', 'status', 'due_at']);
    }

    public function historyForCustomer(string $hqId, string $customerId): Collection
    {
        return TaskRecord::query()
            ->where(['hq_id' => $hqId, 'customer_id' => $customerId])
            // The history is read by time, so a completed task sorts by when it closed and an open one
            // by when it was raised.
            ->orderByRaw('coalesce(completed_at, created_at) desc')
            ->orderByDesc('id')
            ->get(['id', 'title', 'status', 'due_at', 'completed_at', 'created_at']);
    }

    public function listForInbox(string $hqId, ?string $assigneeId, TaskListFiltersDto $filters, DateTimeImmutable $now): Collection
    {
        $query = $this->inbox($hqId, $assigneeId)->with($this->inboxNames());
        if ($filters->bucket !== null) {
            $this->narrowToBucket($query, $filters->bucket, $now);
        } else {
            $query->whereNotIn('status', TaskStatus::closedValues());
        }
        if ($filters->search !== null) {
            $query->whereLike('title', '%'.$filters->search.'%');
        }

        // A task without a due date carries no deadline, so it sorts behind every dated one.
        return $query->orderByRaw('due_at is null')->orderBy('due_at')->orderBy('id')->get();
    }

    public function summaryForInbox(string $hqId, ?string $assigneeId, DateTimeImmutable $now): TaskSummaryDto
    {
        [$startOfDay, $endOfDay] = $this->today($now);
        $open = $this->inbox($hqId, $assigneeId)->whereNotIn('status', TaskStatus::closedValues());

        // One statement for the whole counter row, so the inbox header costs a single query.
        $counts = (clone $open)->selectRaw(
            'count(*) as open_total,'
            .' sum(case when status = ? then 1 else 0 end) as waiting_total,'
            .' sum(case when due_at >= ? and due_at < ? then 1 else 0 end) as today_total,'
            .' sum(case when due_at < ? then 1 else 0 end) as overdue_total,'
            .' sum(case when customer_id is null and opportunity_id is null then 1 else 0 end) as internal_total',
            [TaskStatus::WAITING_CUSTOMER->value, $startOfDay, $endOfDay, $startOfDay],
        )->toBase()->first();

        return new TaskSummaryDto(
            open: (int) ($counts->open_total ?? 0),
            waitingCustomer: (int) ($counts->waiting_total ?? 0),
            today: (int) ($counts->today_total ?? 0),
            overdue: (int) ($counts->overdue_total ?? 0),
            internal: (int) ($counts->internal_total ?? 0),
        );
    }

    public function findForTenant(string $hqId, string $taskId): ?TaskRecord
    {
        return $this->ofTenant($hqId, $taskId)->with($this->inboxNames())->first();
    }

    public function lockForTenant(string $hqId, string $taskId): ?TaskRecord
    {
        return $this->ofTenant($hqId, $taskId)->lockForUpdate()->first();
    }

    public function create(array $attributes): TaskRecord
    {
        return TaskRecord::query()->forceCreate($attributes);
    }

    public function update(string $hqId, string $taskId, array $attributes): void
    {
        $this->ofTenant($hqId, $taskId)->update($attributes);
    }

    public function openForAssignees(string $hqId, array $assigneeIds): Collection
    {
        if ($assigneeIds === []) {
            return TaskRecord::query()->whereRaw('1 = 0')->get();
        }

        return TaskRecord::query()
            ->where('hq_id', $hqId)
            ->whereIn('assignee_id', $assigneeIds)
            ->whereNotIn('status', TaskStatus::closedValues())
            ->orderByRaw('due_at is null')
            ->orderBy('due_at')
            ->get(['id', 'title', 'status', 'due_at', 'assignee_id']);
    }

    /** @return Builder<TaskRecord> */
    private function ofTenant(string $hqId, string $taskId): Builder
    {
        return TaskRecord::query()->where(['hq_id' => $hqId, 'task_id' => $taskId]);
    }

    /** @return Builder<TaskRecord> */
    private function inbox(string $hqId, ?string $assigneeId): Builder
    {
        $query = TaskRecord::query()->where('hq_id', $hqId);

        // Scope "mine" is the actor's own work; "accessible" reads the whole tenant and passes null.
        return $assigneeId === null ? $query : $query->where('assignee_id', $assigneeId);
    }

    /** @param Builder<TaskRecord> $query */
    private function narrowToBucket(Builder $query, TaskBucket $bucket, DateTimeImmutable $now): void
    {
        [$startOfDay, $endOfDay] = $this->today($now);
        $open = fn (Builder $q): Builder => $q->whereNotIn('status', TaskStatus::closedValues());
        match ($bucket) {
            TaskBucket::OVERDUE => $open($query)->where('due_at', '<', $startOfDay),
            TaskBucket::TODAY => $open($query)->where('due_at', '>=', $startOfDay)->where('due_at', '<', $endOfDay),
            TaskBucket::FUTURE => $open($query)->where('due_at', '>=', $endOfDay),
            TaskBucket::UNDATED => $open($query)->whereNull('due_at'),
            TaskBucket::WAITING => $query->where('status', TaskStatus::WAITING_CUSTOMER->value),
            TaskBucket::CLOSED => $query->whereIn('status', TaskStatus::closedValues()),
        };
    }

    /**
     * The calendar day the reader is in. A deadline earlier today is already late, so the overdue and
     * today buckets meet at midnight rather than at the current minute.
     *
     * @return array{string, string}
     */
    private function today(DateTimeImmutable $now): array
    {
        $startOfDay = $now->setTime(0, 0);

        return [$startOfDay->format('Y-m-d H:i:s'), $startOfDay->modify('+1 day')->format('Y-m-d H:i:s')];
    }

    /**
     * The owner name the inbox prints beside each task, read once for the whole page. The customer and
     * the opportunity are named through the ports instead: their tables belong to other modules.
     */
    private function inboxNames(): array
    {
        return ['assignee' => fn ($assignee) => $assignee->select(['id', 'display_name'])];
    }
}
