<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\Repositories;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\CrmTeam\Application\Dto\TeamMemberFiltersDto;
use Modules\CrmTeam\Infrastructure\Persistence\Models\TeamMemberRecord;

interface TeamMemberRepositoryInterface
{
    /** @return LengthAwarePaginator<TeamMemberRecord> */
    public function paginateForTenant(string $hqId, TeamMemberFiltersDto $filters): LengthAwarePaginator;

    public function findCurrent(string $hqId, string $teamId, string $userId): ?TeamMemberRecord;

    public function findForTenant(string $hqId, string $membershipId): ?TeamMemberRecord;

    /** Reads the row for update; the caller must already be inside a transaction. */
    public function lockForTenant(string $hqId, string $membershipId): ?TeamMemberRecord;

    /**
     * The current memberships of the named users, whatever team they sit in.
     *
     * @param  list<string>  $userIds
     * @return list<TeamMemberRecord>
     */
    public function currentForUsers(string $hqId, array $userIds, ?string $teamId = null): array;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): TeamMemberRecord;

    /** @param array<string, mixed> $attributes */
    public function update(string $hqId, string $membershipId, array $attributes): void;
}
