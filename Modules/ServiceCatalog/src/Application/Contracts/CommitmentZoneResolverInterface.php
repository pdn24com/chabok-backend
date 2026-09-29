<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\CoverageAddress;
use Modules\ServiceCatalog\Application\Dto\CommitmentDestinationsDto;
use Modules\ServiceCatalog\Application\Dto\CommitmentZoneGroupDto;

interface CommitmentZoneResolverInterface
{
    /** @return list<CommitmentZoneGroupDto> */
    public function groups(string $hqId): array;

    /** Tenant-scoped current published group and its zone codes. */
    public function group(
        string $hqId,
        string $groupId,
        bool $locking = false,
    ): CommitmentZoneGroupDto;

    /** @param list<string> $groupIds */
    public function destinations(string $hqId, array $groupIds, CoverageAddress $address): CommitmentDestinationsDto;
}
