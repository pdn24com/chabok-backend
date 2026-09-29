<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\Repositories;

use DateTimeImmutable;
use Illuminate\Database\Eloquent\Collection;
use Modules\CrmTask\Application\Dto\TaskListFiltersDto;
use Modules\CrmTask\Application\Dto\TaskSummaryDto;
use Modules\CrmTask\Infrastructure\Persistence\Models\TaskRecord;

interface TaskRepositoryInterface
{
    /**
     * Every unfinished task of one customer, the soonest due date first.
     *
     * @return Collection<int, TaskRecord>
     */
    public function openForCustomer(string $hqId, string $customerId): Collection;

    /**
     * Every task of one customer whatever its status, newest first, for the history page.
     *
     * @return Collection<int, TaskRecord>
     */
    public function historyForCustomer(string $hqId, string $customerId): Collection;

    /**
     * The task inbox: every task the scope allows, narrowed by the bucket and ordered by deadline with
     * the undated ones last. An assignee of null reads every task of the tenant.
     *
     * @return Collection<int, TaskRecord>
     */
    public function listForInbox(string $hqId, ?string $assigneeId, TaskListFiltersDto $filters, DateTimeImmutable $now): Collection;

    /** The counters above the inbox, each counting the rows its matching bucket lists. */
    public function summaryForInbox(string $hqId, ?string $assigneeId, DateTimeImmutable $now): TaskSummaryDto;

    /**
     * The unfinished tasks of the named people, so a membership change can show the work it touches
     * before it is committed.
     *
     * @param  list<string>  $assigneeIds
     * @return Collection<int, TaskRecord>
     */
    public function openForAssignees(string $hqId, array $assigneeIds): Collection;

    public function findForTenant(string $hqId, string $taskId): ?TaskRecord;

    /** Reads the row for update; the caller must already be inside a transaction. */
    public function lockForTenant(string $hqId, string $taskId): ?TaskRecord;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): TaskRecord;

    /** @param array<string, mixed> $attributes */
    public function update(string $hqId, string $taskId, array $attributes): void;
}
