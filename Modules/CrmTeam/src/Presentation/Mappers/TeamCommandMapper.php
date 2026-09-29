<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Presentation\Mappers;

use DateTimeImmutable;
use Modules\CrmTeam\Application\Dto\BulkMembershipDto;
use Modules\CrmTeam\Application\Dto\TeamChangesDto;
use Modules\CrmTeam\Application\Dto\TeamDraftDto;
use Modules\CrmTeam\Application\Dto\TeamMemberFiltersDto;
use Modules\CrmTeam\Application\UseCases\AddTeamMember\AddTeamMemberCommand;
use Modules\CrmTeam\Application\UseCases\CreateTeam\CreateTeamCommand;
use Modules\CrmTeam\Application\UseCases\EndTeamMembership\EndTeamMembershipCommand;
use Modules\CrmTeam\Application\UseCases\ListTeamMembers\ListTeamMembersCommand;
use Modules\CrmTeam\Application\UseCases\ListTeams\ListTeamsCommand;
use Modules\CrmTeam\Application\UseCases\RunBulkMembershipChange\RunBulkMembershipChangeCommand;
use Modules\CrmTeam\Application\UseCases\UpdateTeam\UpdateTeamCommand;
use Modules\CrmTeam\Domain\Enums\BulkMembershipOperation;
use Modules\CrmTeam\Domain\Enums\MembershipStatus;
use Modules\CrmTeam\Domain\Enums\TeamStatus;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final class TeamCommandMapper
{
    public static function listing(AuthenticatedPrincipal $actor, array $input): ListTeamsCommand
    {
        return new ListTeamsCommand($actor, isset($input['status']) ? TeamStatus::from($input['status']) : null);
    }

    public static function draft(AuthenticatedPrincipal $actor, array $input): CreateTeamCommand
    {
        return new CreateTeamCommand($actor, new TeamDraftDto(
            title: $input['title'],
            supervisorUserId: (string) $input['supervisor_user_id'],
            status: isset($input['status']) ? TeamStatus::from($input['status']) : TeamStatus::ACTIVE,
            parentTeamId: isset($input['parent_team_id']) ? (string) $input['parent_team_id'] : null,
        ));
    }

    public static function changes(AuthenticatedPrincipal $actor, string $teamId, array $input): UpdateTeamCommand
    {
        return new UpdateTeamCommand($actor, $teamId, new TeamChangesDto(
            title: $input['title'] ?? null,
            supervisorUserId: isset($input['supervisor_user_id']) ? (string) $input['supervisor_user_id'] : null,
            status: isset($input['status']) ? TeamStatus::from($input['status']) : null,
            // array_key_exists, not isset: an explicit null detaches the team and must reach the handler.
            parentTeamId: isset($input['parent_team_id']) ? (string) $input['parent_team_id'] : null,
            parentSpecified: array_key_exists('parent_team_id', $input),
        ));
    }

    public static function memberListing(AuthenticatedPrincipal $actor, array $input): ListTeamMembersCommand
    {
        return new ListTeamMembersCommand($actor, new TeamMemberFiltersDto(
            page: (int) ($input['page'] ?? 1),
            perPage: (int) ($input['per_page'] ?? 25),
            teamId: isset($input['team_id']) ? (string) $input['team_id'] : null,
            status: isset($input['status']) ? MembershipStatus::from($input['status']) : null,
            search: $input['q'] ?? null,
        ));
    }

    public static function member(AuthenticatedPrincipal $actor, string $teamId, array $input): AddTeamMemberCommand
    {
        return new AddTeamMemberCommand($actor, $teamId, (string) $input['user_id'], self::moment($input['valid_from'] ?? null));
    }

    public static function membershipEnd(AuthenticatedPrincipal $actor, string $membershipId, array $input): EndTeamMembershipCommand
    {
        return new EndTeamMembershipCommand(
            actor: $actor,
            membershipId: $membershipId,
            validTo: self::moment($input['valid_to'] ?? null),
            replacementUserId: isset($input['replacement_user_id']) ? (string) $input['replacement_user_id'] : null,
        );
    }

    public static function bulk(AuthenticatedPrincipal $actor, array $input, bool $previewOnly): RunBulkMembershipChangeCommand
    {
        return new RunBulkMembershipChangeCommand($actor, new BulkMembershipDto(
            operation: BulkMembershipOperation::from($input['operation']),
            userIds: array_map(static fn ($id): string => (string) $id, $input['user_ids']),
            sourceTeamId: isset($input['source_team_id']) ? (string) $input['source_team_id'] : null,
            targetTeamId: isset($input['target_team_id']) ? (string) $input['target_team_id'] : null,
            replacementUserId: isset($input['replacement_user_id']) ? (string) $input['replacement_user_id'] : null,
            confirmOpenTasks: (bool) ($input['confirm_open_tasks'] ?? false),
        ), $previewOnly);
    }

    private static function moment(?int $timestamp): ?DateTimeImmutable
    {
        return $timestamp === null ? null : (new DateTimeImmutable)->setTimestamp($timestamp);
    }
}
