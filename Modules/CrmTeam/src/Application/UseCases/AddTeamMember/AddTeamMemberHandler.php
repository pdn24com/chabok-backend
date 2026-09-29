<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\UseCases\AddTeamMember;

use Illuminate\Database\ConnectionInterface;
use Modules\CrmTeam\Application\Contracts\TeamAccessGuardInterface;
use Modules\CrmTeam\Application\Repositories\MembershipEventRepositoryInterface;
use Modules\CrmTeam\Application\Repositories\TeamMemberRepositoryInterface;
use Modules\CrmTeam\Application\Repositories\TeamRepositoryInterface;
use Modules\CrmTeam\Domain\Enums\MembershipEventType;
use Modules\CrmTeam\Domain\Enums\MembershipStatus;
use Modules\CrmTeam\Domain\Enums\TeamStatus;
use Modules\CrmTeam\Infrastructure\Persistence\Models\TeamMemberRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;

/**
 * Enrols a Chabok user in a CRM team. The CRM role itself lives in IAM, so nothing here grants access:
 * this row only records who is currently in which team.
 */
final readonly class AddTeamMemberHandler
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

    public function handle(AddTeamMemberCommand $command): TeamMemberRecord
    {
        $hqId = $this->accessGuard->assertCanManage($command->actor);

        return $this->connection->transaction(function () use ($command, $hqId): TeamMemberRecord {
            $team = $this->teamRepository->findForTenant($hqId, $command->teamId)
                ?? throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            if ($team->status !== TeamStatus::ACTIVE) {
                throw $this->invalid('team_id', 'team.retired_team_takes_no_new_member');
            }
            if ($this->userRepository->findByTenant($hqId, $command->userId)?->status !== 'ACTIVE') {
                throw $this->invalid('user_id', 'team.select_active_user_of_the_tenant');
            }
            // One current membership per tenant, team and user; the database holds the same rule.
            if ($this->teamMemberRepository->findCurrent($hqId, $command->teamId, $command->userId) !== null) {
                throw $this->invalid('user_id', 'team.user_is_already_a_current_member');
            }

            $at = $this->clock->now();
            $membership = $this->teamMemberRepository->create([
                'hq_id' => $hqId,
                'team_id' => $command->teamId,
                'user_id' => $command->userId,
                'valid_from' => $command->validFrom ?? $at,
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
                'after_snapshot' => ['team_id' => $command->teamId, 'user_id' => $command->userId, 'status' => MembershipStatus::ACTIVE->value],
                'occurred_at' => $at,
                'created_by' => $command->actor->userId,
                'created_at' => $at,
            ]);

            return $this->teamMemberRepository->findForTenant($hqId, $membership->team_member_id) ?? $membership;
        }, attempts: 3);
    }

    private function invalid(string $field, string $messageKey): ApiException
    {
        return new ApiException(ApiErrorCode::ValidationError, 422, 'foundation.request_is_invalid', [$field => [$messageKey]]);
    }
}
