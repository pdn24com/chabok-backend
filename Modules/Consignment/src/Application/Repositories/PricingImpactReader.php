<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Repositories;

interface PricingImpactReader
{
    public function acceptedZoneVersion(string $hqId, ?string $snapshotId): ?object;

    public function zoneSetId(string $versionId): ?string;

    public function commitmentScheduleId(string $versionId): ?string;

    public function publishedCommitmentPolicy(?string $schedule): ?string;

    public function geographicMemberTypes(string $hqId, array $groups): array;
}
