<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\UseCases\UpdateTeam;

use Illuminate\Database\ConnectionInterface;
use Modules\CrmTeam\Application\Contracts\TeamAccessGuardInterface;
use Modules\CrmTeam\Application\Repositories\MembershipEventRepositoryInterface;
use Modules\CrmTeam\Application\Repositories\TeamMemberRepositoryInterface;
use Modules\CrmTeam\Application\Repositories\TeamRepositoryInterface;
use Modules\CrmTeam\Domain\Enums\MembershipEventType;
use Modules\CrmTeam\Domain\Enums\MembershipStatus;
use Modules\CrmTeam\Infrastructure\Persistence\Models\TeamRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;

final readonly class UpdateTeamHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private TeamAccessGuardInterface $accessGuard,
        private UserRepositoryInterface $userRepository,
        private TeamRepositoryInterface $teamRepository,
        private TeamMemberRepositoryInterface $teamMemberRepository,
        private MembershipEventRepositoryInterface $membershipEventRepository,
    ) {}

    public function handle(UpdateTeamCommand $command): UpdateTeamResult
    {
        $hqId = $this->accessGuard->assertCanManage($command->actor);
        $changes = $command->changes;
        if ($changes->touchesNothing()) {
            throw $this->invalid('*', 'team.change_is_empty');
        }

        $result = $this->connection->transaction(function () use ($command, $hqId, $changes): TeamRecord {
            $current = $this->teamRepository->lockForTenant($hqId, $command->teamId)
                ?? throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');

            $attributes = [];
            if ($changes->title !== null) {
                if ($this->teamRepository->titleTaken($hqId, $changes->title, $command->teamId)) {
                    throw $this->invalid('title', 'team.title_is_already_used');
                }
                $attributes['title'] = $changes->title;
            }
            if ($changes->status !== null) {
                $attributes['status'] = $changes->status->value;
            }
            if ($changes->parentSpecified) {
                // A team cannot be its own parent; the deeper cycle check waits on the hierarchy the
                // model reserves but does not require yet.
                if ($changes->parentTeamId === $command->teamId) {
                    throw $this->invalid('parent_team_id', 'team.cannot_be_its_own_parent');
                }
                if ($changes->parentTeamId !== null && $this->teamRepository->findForTenant($hqId, $changes->parentTeamId) === null) {
                    throw $this->invalid('parent_team_id', 'team.select_parent_team_of_the_tenant');
                }
                $attributes['parent_team_id'] = $changes->parentTeamId;
            }
            if ($changes->supervisorUserId !== null) {
                $this->moveSupervisor($hqId, $command, $changes->supervisorUserId);
                $attributes['supervisor_user_id'] = $changes->supervisorUserId;
            }
            $this->teamRepository->update($hqId, $command->teamId, $attributes);

            return $this->teamRepository->findForTenant($hqId, $command->teamId) ?? $current;
        }, attempts: 3);

        return new UpdateTeamResult($result);
    }

    /** The supervisor has to be an active member, so naming a new one enrols them if they are not already. */
    private function moveSupervisor(string $hqId, UpdateTeamCommand $command, string $supervisorUserId): void
    {
        if ($this->userRepository->findByTenant($hqId, $supervisorUserId)?->status !== 'ACTIVE') {
            throw $this->invalid('supervisor_user_id', 'team.select_active_supervisor_from_tenant_users');
        }
        if ($this->teamMemberRepository->findCurrent($hqId, $command->teamId, $supervisorUserId) !== null) {
            return;
        }
        $at = $this->clock->now();
        $membership = $this->teamMemberRepository->create([
            'hq_id' => $hqId,
            'team_id' => $command->teamId,
            'user_id' => $supervisorUserId,
            'valid_from' => $at,
            'status' => MembershipStatus::ACTIVE->value,
            'created_by' => $command->actor->userId,
            'created_at' => $at,
        ]);
        $this->membershipEventRepository->create([
            'hq_id' => $hqId,
            'membership_id' => $membership->team_member_id,
            'actor_id' => $command->actor->userId,
            'event_type' => MembershipEventType::ADD->value,
            'before_snapshot' => null,
            'after_snapshot' => ['team_id' => $command->teamId, 'user_id' => $supervisorUserId, 'status' => MembershipStatus::ACTIVE->value],
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
