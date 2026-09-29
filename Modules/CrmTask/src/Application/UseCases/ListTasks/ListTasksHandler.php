<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\UseCases\ListTasks;

use Modules\CrmTask\Application\Contracts\TaskAccessGuardInterface;
use Modules\CrmTask\Application\Ports\CustomerDirectoryInterface;
use Modules\CrmTask\Application\Ports\OpportunityDirectoryInterface;
use Modules\CrmTask\Application\Repositories\TaskRepositoryInterface;
use Modules\CrmTask\Domain\Enums\TaskScope;
use Modules\CrmTask\Infrastructure\Persistence\Models\TaskRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;

final readonly class ListTasksHandler
{
    public function __construct(
        private ClockInterface $clock,
        private TaskAccessGuardInterface $accessGuard,
        private TaskRepositoryInterface $taskRepository,
        private CustomerDirectoryInterface $customerDirectory,
        private OpportunityDirectoryInterface $opportunityDirectory,
    ) {}

    public function handle(ListTasksCommand $command): ListTasksResult
    {
        $hqId = $this->accessGuard->assertCanRead($command->actor);
        // "mine" is the actor's own queue; "accessible" reads every task the tenant holds.
        $assigneeId = $command->filters->scope === TaskScope::MINE ? $command->actor->userId : null;
        $now = $this->clock->now();

        $tasks = $this->taskRepository->listForInbox($hqId, $assigneeId, $command->filters, $now);
        $summary = $this->taskRepository->summaryForInbox($hqId, $assigneeId, $now);

        return new ListTasksResult(
            $summary,
            $tasks,
            $this->customerDirectory->displayNamesFor($hqId, $this->idsOf($tasks, 'customer_id')),
            $this->opportunityDirectory->titlesFor($hqId, $this->idsOf($tasks, 'opportunity_id')),
        );
    }

    /**
     * @param  iterable<TaskRecord>  $tasks
     * @return list<string>
     */
    private function idsOf(iterable $tasks, string $column): array
    {
        $ids = [];
        foreach ($tasks as $task) {
            $id = $task->getAttribute($column);
            if ($id !== null) {
                $ids[(string) $id] = true;
            }
        }

        return array_keys($ids);
    }
}
