<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\UseCases\EndTeamMembership;

use Illuminate\Database\ConnectionInterface;
use Modules\CrmTask\Application\Repositories\TaskRepositoryInterface;
use Modules\CrmTeam\Application\Contracts\TeamAccessGuardInterface;
use Modules\CrmTeam\Application\Repositories\MembershipEventRepositoryInterface;
use Modules\CrmTeam\Application\Repositories\TeamMemberRepositoryInterface;
use Modules\CrmTeam\Application\Repositories\TeamRepositoryInterface;
use Modules\CrmTeam\Domain\Enums\MembershipEventType;
use Modules\CrmTeam\Domain\Enums\MembershipStatus;
use Modules\CrmTeam\Infrastructure\Persistence\Models\TeamMemberRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;

/**
 * Ends a membership without deleting it, so the history of who was in the team survives. Ending the
 * supervisor's own membership is refused: the team would be left without the active supervisor it requires.
 */
final readonly class EndTeamMembershipHandler
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

    public function handle(EndTeamMembershipCommand $command): EndTeamMembershipResult
    {
        $hqId = $this->accessGuard->assertCanManage($command->actor);

        $result = $this->connection->transaction(function () use ($command, $hqId): TeamMemberRecord {
            $membership = $this->teamMemberRepository->lockForTenant($hqId, $command->membershipId)
                ?? throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            if ($membership->status !== MembershipStatus::ACTIVE) {
                throw $this->invalid('membership_id', 'team.membership_has_already_ended');
            }

            $teamId = (string) $membership->team_id;
            $userId = (string) $membership->user_id;
            $team = $this->teamRepository->findForTenant($hqId, $teamId);
            if ($team !== null && (string) $team->supervisor_user_id === $userId) {
                throw $this->invalid('membership_id', 'team.name_a_new_supervisor_before_ending_this_one');
            }

            $at = $this->clock->now();
            $validTo = $command->validTo ?? $at;
            // The stored window has to stay coherent: an end before the start is refused rather than saved.
            if ($validTo < $membership->valid_from) {
                throw $this->invalid('valid_to', 'team.membership_cannot_end_before_it_started');
            }
            $this->reassignOpenWork($hqId, $userId, $command->replacementUserId);

            $this->teamMemberRepository->update($hqId, $command->membershipId, [
                'valid_to' => $validTo,
                'status' => MembershipStatus::ENDED->value,
            ]);
            $this->membershipEventRepository->create([
                'hq_id' => $hqId,
                'membership_id' => $command->membershipId,
                'actor_id' => $command->actor->userId,
                'event_type' => MembershipEventType::END->value,
                'before_snapshot' => ['team_id' => $teamId, 'user_id' => $userId, 'status' => MembershipStatus::ACTIVE->value],
                'after_snapshot' => ['team_id' => $teamId, 'user_id' => $userId, 'status' => MembershipStatus::ENDED->value, 'valid_to' => $validTo->format(DATE_ATOM)],
                'occurred_at' => $at,
                'created_by' => $command->actor->userId,
                'created_at' => $at,
            ]);

            return $this->teamMemberRepository->findForTenant($hqId, $command->membershipId) ?? $membership;
        }, attempts: 3);

        return new EndTeamMembershipResult($result);
    }

    /** Leaving a team does not close the work in hand, so it is handed over or left with its owner. */
    private function reassignOpenWork(string $hqId, string $userId, ?string $replacementUserId): void
    {
        if ($replacementUserId === null) {
            return;
        }
        if ($this->userRepository->findByTenant($hqId, $replacementUserId)?->status !== 'ACTIVE') {
            throw $this->invalid('replacement_user_id', 'team.select_active_user_of_the_tenant');
        }
        foreach ($this->taskRepository->openForAssignees($hqId, [$userId]) as $task) {
            $this->taskRepository->update($hqId, $task->task_id, ['assignee_id' => $replacementUserId]);
        }
    }

    private function invalid(string $field, string $messageKey): ApiException
    {
        return new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid', [$field => [$messageKey]]);
    }
}
