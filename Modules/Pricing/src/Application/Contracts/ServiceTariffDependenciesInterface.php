<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Contracts;

use Carbon\CarbonImmutable;
use Modules\Pricing\Application\Dto\ServiceTariffResolutionDto;
use Modules\Pricing\Application\Enums\ServiceDependencyFailure;

interface ServiceTariffDependenciesInterface
{
    public function ids(string $versionId): array;

    public function replace(string $versionId, array $ids): void;

    public function resolve(array $ids, string $hqId, ?string $parentZoneVersionId, CarbonImmutable $asOf, array $localChargeTypeIds): array;

    public function inspect(array $ids, string $hqId, ?string $parentZoneVersionId, CarbonImmutable $asOf, array $localChargeTypeIds): ServiceTariffResolutionDto;

    public function chargeKey(string $code): string;

    public function successorFailure(string $kind, string $familyId, ?string $zoneVersionId): ?ServiceDependencyFailure;
}
