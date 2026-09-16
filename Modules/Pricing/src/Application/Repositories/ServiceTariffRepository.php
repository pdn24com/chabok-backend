<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Repositories;

interface ServiceTariffRepository
{
    public function attachedFamilyIds(string $versionId): array;

    public function deleteAttachments(string $versionId): void;

    public function attachFamily(string $versionId, string $id): void;

    public function zoneGroup(string $versionId): ?string;

    public function chargeCode(string $chargeId): ?string;

    public function serviceFamily(string $hqId, string $id): ?object;

    public function publishedVersion(string $id, \DateTimeInterface $asOf): ?object;

    public function hasIncompatibleParents(string $familyId, ?string $group, \DateTimeInterface $at): bool;
}
