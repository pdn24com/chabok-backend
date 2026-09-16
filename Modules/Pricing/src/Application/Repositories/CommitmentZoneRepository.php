<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Repositories;

interface CommitmentZoneRepository
{
    public function availableGroups(string $hqId, \DateTimeInterface $at): array;

    public function zoneTitles(string $versionId): array;

    public function visibleGroup(string $hqId, string $groupId, bool $locking): ?object;

    public function currentVersion(string $groupId, \DateTimeInterface $at): ?object;

    public function zoneCodes(string $versionId): array;

    public function members(string $versionId): array;
}
