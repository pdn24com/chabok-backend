<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmTeam\Domain\Enums\TeamStatus;
use Modules\CrmTeam\Infrastructure\Persistence\Models\TeamRecord;

interface TeamRepositoryInterface
{
    /** True when the tenant owns an active team under this ID. */
    public function activeExistsForTenant(string $hqId, string $teamId): bool;

    /**
     * The teams of a tenant with their supervisor and how many people are currently in each.
     *
     * @return Collection<int, TeamRecord>
     */
    public function listForTenant(string $hqId, ?TeamStatus $status): Collection;

    public function findForTenant(string $hqId, string $teamId): ?TeamRecord;

    /** Reads the row for update; the caller must already be inside a transaction. */
    public function lockForTenant(string $hqId, string $teamId): ?TeamRecord;

    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): TeamRecord;

    /** @param array<string, mixed> $attributes */
    public function update(string $hqId, string $teamId, array $attributes): void;

    /** True when another team of the tenant already carries the title. */
    public function titleTaken(string $hqId, string $title, ?string $exceptTeamId = null): bool;
}
