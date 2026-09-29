<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Modules\ServiceCatalog\Application\Dto\OfferingDependencyVersionsDto;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceOfferingVersionRecord;

interface OfferingDependenciesInterface
{
    /** @param Collection<int, ServiceOfferingVersionRecord> $offerings @return array<string, OfferingDependencyVersionsDto> */
    public function currentFor(Collection $offerings, string $hqId): array;

    public function assertAvailable(OfferingDependencyVersionsDto $dependencies): void;

    public function available(ServiceOfferingVersionRecord $version, string $hqId, string $channel): bool;
}
