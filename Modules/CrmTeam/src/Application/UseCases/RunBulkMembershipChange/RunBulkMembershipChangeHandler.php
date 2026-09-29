<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\UseCases\RunBulkMembershipChange;

use DateTimeImmutable;
use Illuminate\Database\ConnectionInterface;
use Modules\CrmTask\Application\Repositories\TaskRepositoryInterface;
use Modules\CrmTeam\Application\Contracts\TeamAccessGuardInterface;
use Modules\CrmTeam\Application\Dto\BulkMembershipDto;
use Modules\CrmTeam\Application\Dto\BulkMembershipPreviewDto;
use Modules\CrmTeam\Application\Repositories\MembershipEventRepositoryInterface;
use Modules\CrmTeam\Application\Repositories\TeamMemberRepositoryInterface;
use Modules\CrmTeam\Application\Repositories\TeamRepositoryInterface;
use Modules\CrmTeam\Domain\Enums\BulkMembershipOperation;
use Modules\CrmTeam\Domain\Enums\MembershipEventType;
use Modules\CrmTeam\Domain\Enums\MembershipStatus;
use Modules\CrmTeam\Domain\Enums\TeamStatus;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;

/**
 * Adds, ends or transfers several memberships as one action. The same call with previewOnly reports what
 * would change and writes nothing, so the operator sees the open work before committing to it. Every row
 * written shares operationId, which is what ties a run together in the trail.
 */
final readonly class RunBulkMembershipChangeHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private TeamAccessGuardInterface $accessGuard,
        private UserRepositoryInterface $userRepository,
        private TeamRepositoryInterface $teamRepository,
        private TeamMemberRepositoryInterface $teamMemberRepository,
        private MembershipEventRepositoryInterface $membershipEventRepository,
        private TaskRepositoryInterface $taskRepository,
    ) {}

    public function handle(RunBulkMembershipChangeCommand $command): RunBulkMembershipChangeResult
    {
        $hqId = $command->previewOnly
            ? $this->accessGuard->assertCanRead($command->actor)
            : $this->accessGuard->assertCanManage($command->actor);
        $input = $command->input;
        $this->assertTeamsFit($hqId, $input);

        $sourceTeamId = $input->operation === BulkMembershipOperation::ADD ? null : $input->sourceTeamId;
        $memberships = $this->teamMemberRepository->currentForUsers($hqId, $input->userIds, $sourceTeamId);
        $affectedTasks = [];
        foreach ($this->taskRepository->openForAssignees($hqId, $input->userIds) as $task) {
            $affectedTasks[] = [
                'task_id' => $task->task_id,
                'title' => $task->title,
                'assignee_id' => (string) $task->assignee_id,
            ];
        }
        $preview = new BulkMembershipPreviewDto($memberships, $affectedTasks);

        if ($command->previewOnly) {
            return new RunBulkMembershipChangeResult($preview);
        }
        // Moving people who still hold open work is allowed, but only with eyes open.
        if ($affectedTasks !== [] && ! $input->confirmOpenTasks) {
            throw $this->invalid('confirm_open_tasks', 'team.confirm_the_open_work_this_run_touches');
        }

        return $this->connection->transaction(function () use ($command, $hqId, $input, $preview): RunBulkMembershipChangeResult {
            $at = $this->clock->now();
            $events = [];
            foreach ($input->userIds as $userId) {
                $events[] = match ($input->operation) {
                    BulkMembershipOperation::ADD => $this->add($hqId, $command, $userId, $at),
                    BulkMembershipOperation::END => $this->end($hqId, $command, $userId, $at),
                    BulkMembershipOperation::TRANSFER => $this->transfer($hqId, $command, $userId, $at),
                };
            }
            $this->handOverOpenWork($hqId, $input);
            $written = array_values(array_filter($events));
            // The first row of the run names the run: every row of it then carries that one correlation.
            if ($written !== []) {
                $operationId = $written[0]->membership_event_id;
                $this->membershipEventRepository->correlate($hqId, array_map(
                    static fn ($event): string => $event->membership_event_id, $written), $operationId);
                foreach ($written as $event) {
                    $event->setAttribute('operation_id', $operationId);
                }
            }

            return new RunBulkMembershipChangeResult($preview, $written);
        }, attempts: 3);
    }

    private function assertTeamsFit(string $hqId, BulkMembershipDto $input): void
    {
        if ($input->userIds === []) {
            throw $this->invalid('user_ids', 'team.name_at_least_one_user');
        }
        $needsTarget = $input->operation !== BulkMembershipOperation::END;
        if ($needsTarget) {
            $target = $input->targetTeamId === null ? null : $this->teamRepository->findForTenant($hqId, $input->targetTeamId);
            if ($target === null) {
                throw $this->invalid('target_team_id', 'team.select_team_of_the_tenant');
            }
            if ($target->status !== TeamStatus::ACTIVE) {
                throw $this->invalid('target_team_id', 'team.retired_team_takes_no_new_member');
            }
        }
        $needsSource = $input->operation !== BulkMembershipOperation::ADD;
        if ($needsSource && ($input->sourceTeamId === null || $this->teamRepository->findForTenant($hqId, $input->sourceTeamId) === null)) {
            throw $this->invalid('source_team_id', 'team.select_team_of_the_tenant');
        }
        if ($input->operation === BulkMembershipOperation::TRANSFER && $input->sourceTeamId === $input->targetTeamId) {
            throw $this->invalid('target_team_id', 'team.transfer_must_change_the_team');
        }
    }

    private function add(string $hqId, RunBulkMembershipChangeCommand $command, string $userId, DateTimeImmutable $at): mixed
    {
        $input = $command->input;
        if ($this->userRepository->findByTenant($hqId, $userId)?->status !== 'ACTIVE') {
            throw $this->invalid('user_ids', 'team.select_active_user_of_the_tenant');
        }
        // Already in the team is not an error in a bulk run; the run stays idempotent per person.
        if ($this->teamMemberRepository->findCurrent($hqId, $input->targetTeamId, $userId) !== null) {
            return null;
        }
        $membership = $this->teamMemberRepository->create([
            'hq_id' => $hqId,
            'team_id' => $input->targetTeamId,
            'user_id' => $userId,
            'valid_from' => $at,
            'status' => MembershipStatus::ACTIVE->value,
            'created_by' => $command->actor->userId,
            'created_at' => $at,
        ]);

        return $this->record($hqId, $command, $membership->team_member_id, MembershipEventType::ADD, null,
            ['team_id' => $input->targetTeamId, 'user_id' => $userId, 'status' => MembershipStatus::ACTIVE->value], $at);
    }

    private function end(string $hqId, RunBulkMembershipChangeCommand $command, string $userId, DateTimeImmutable $at): mixed
    {
        $input = $command->input;
        $membership = $this->teamMemberRepository->findCurrent($hqId, $input->sourceTeamId, $userId);
        if ($membership === null) {
            return null;
        }
        $this->teamMemberRepository->update($hqId, $membership->team_member_id, [
            'valid_to' => $at,
            'status' => MembershipStatus::ENDED->value,
        ]);

        return $this->record($hqId, $command, $membership->team_member_id, MembershipEventType::END,
            ['team_id' => $input->sourceTeamId, 'user_id' => $userId, 'status' => MembershipStatus::ACTIVE->value],
            ['team_id' => $input->sourceTeamId, 'user_id' => $userId, 'status' => MembershipStatus::ENDED->value], $at);
    }

    /** A transfer ends the membership of the source team and opens one in the target, as a single event. */
    private function transfer(string $hqId, RunBulkMembershipChangeCommand $command, string $userId, DateTimeImmutable $at): mixed
    {
        $input = $command->input;
        $from = $this->teamMemberRepository->findCurrent($hqId, $input->sourceTeamId, $userId);
        if ($from === null) {
            return null;
        }
        $this->teamMemberRepository->update($hqId, $from->team_member_id, [
            'valid_to' => $at,
            'status' => MembershipStatus::ENDED->value,
        ]);
        $to = $this->teamMemberRepository->findCurrent($hqId, $input->targetTeamId, $userId)
            ?? $this->teamMemberRepository->create([
                'hq_id' => $hqId,
                'team_id' => $input->targetTeamId,
                'user_id' => $userId,
                'valid_from' => $at,
                'status' => MembershipStatus::ACTIVE->value,
                'created_by' => $command->actor->userId,
                'created_at' => $at,
            ]);

        return $this->record($hqId, $command, $to->team_member_id, MembershipEventType::TRANSFER,
            ['team_id' => $input->sourceTeamId, 'user_id' => $userId, 'status' => MembershipStatus::ACTIVE->value],
            ['team_id' => $input->targetTeamId, 'user_id' => $userId, 'status' => MembershipStatus::ACTIVE->value], $at);
    }

    private function handOverOpenWork(string $hqId, BulkMembershipDto $input): void
    {
        if ($input->replacementUserId === null) {
            return;
        }
        if ($this->userRepository->findByTenant($hqId, $input->replacementUserId)?->status !== 'ACTIVE') {
            throw $this->invalid('replacement_user_id', 'team.select_active_user_of_the_tenant');
        }
        foreach ($this->taskRepository->openForAssignees($hqId, $input->userIds) as $task) {
            $this->taskRepository->update($hqId, $task->task_id, ['assignee_id' => $input->replacementUserId]);
        }
    }

    private function record(string $hqId, RunBulkMembershipChangeCommand $command, string $membershipId,
        MembershipEventType $type, ?array $before, ?array $after, DateTimeImmutable $at): mixed
    {
        return $this->membershipEventRepository->create([
            'hq_id' => $hqId,
            'membership_id' => $membershipId,
            'actor_id' => $command->actor->userId,
            'event_type' => $type->value,
            'before_snapshot' => $before,
            'after_snapshot' => $after,
            'occurred_at' => $at,
            'created_by' => $command->actor->userId,
            'created_at' => $at,
        ]);
    }

    private function invalid(string $field, string $messageKey): ApiException
    {
        return new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid', [$field => [$messageKey]]);
    }
}
