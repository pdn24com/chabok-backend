<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\UseCases\CreateTeam;

use Illuminate\Database\ConnectionInterface;
use Modules\CrmTeam\Application\Contracts\TeamAccessGuardInterface;
use Modules\CrmTeam\Application\Repositories\MembershipEventRepositoryInterface;
use Modules\CrmTeam\Application\Repositories\TeamMemberRepositoryInterface;
use Modules\CrmTeam\Application\Repositories\TeamRepositoryInterface;
use Modules\CrmTeam\Domain\Enums\MembershipEventType;
use Modules\CrmTeam\Domain\Enums\MembershipStatus;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;

/**
 * Creating a team and putting its supervisor in it is one action: the model requires the supervisor to be
 * an active member, so the two rows are written together or not at all.
 */
final readonly class CreateTeamHandler
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

    public function handle(CreateTeamCommand $command): CreateTeamResult
    {
        $hqId = $this->accessGuard->assertCanManage($command->actor);
        $input = $command->input;

        return $this->connection->transaction(function () use ($command, $hqId, $input): CreateTeamResult {
            if ($this->userRepository->findByTenant($hqId, $input->supervisorUserId)?->status !== 'ACTIVE') {
                throw $this->invalid('supervisor_user_id', 'team.select_active_supervisor_from_tenant_users');
            }
            if ($this->teamRepository->titleTaken($hqId, $input->title)) {
                throw $this->invalid('title', 'team.title_is_already_used');
            }
            if ($input->parentTeamId !== null && $this->teamRepository->findForTenant($hqId, $input->parentTeamId) === null) {
                throw $this->invalid('parent_team_id', 'team.select_parent_team_of_the_tenant');
            }

            $at = $this->clock->now();
            $team = $this->teamRepository->create([
                'hq_id' => $hqId,
                'title' => $input->title,
                'parent_team_id' => $input->parentTeamId,
                'status' => $input->status->value,
                'supervisor_user_id' => $input->supervisorUserId,
                'created_by' => $command->actor->userId,
                'created_at' => $at,
            ]);
            $membership = $this->teamMemberRepository->create([
                'hq_id' => $hqId,
                'team_id' => $team->team_id,
                'user_id' => $input->supervisorUserId,
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
                'after_snapshot' => ['team_id' => $team->team_id, 'user_id' => $input->supervisorUserId, 'status' => MembershipStatus::ACTIVE->value],
                'occurred_at' => $at,
                'created_by' => $command->actor->userId,
                'created_at' => $at,
            ]);

            return new CreateTeamResult($this->teamRepository->findForTenant($hqId, $team->team_id) ?? $team, $membership);
        }, attempts: 3);
    }

    private function invalid(string $field, string $messageKey): ApiException
    {
        return new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid', [$field => [$messageKey]]);
    }
}
