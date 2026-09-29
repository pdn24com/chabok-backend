<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Repositories;

use Modules\Pricing\Application\Dto\PricingVersionPeriodDto;
use Modules\Pricing\Domain\Enums\PricingResource;
use Modules\Pricing\Infrastructure\Persistence\Contracts\PricingIdentityRecordInterface;
use Modules\Pricing\Infrastructure\Persistence\Contracts\PricingVersionRecordInterface;

/**
 * Tariffs and Zone Sets share one lifecycle: a stable identity owns an ordered chain of versions.
 * Every method takes the resource it acts on instead of each caller choosing a model.
 */
interface PricingVersionRepositoryInterface
{
    public function identityVisible(PricingResource $resource, string $identityId, ?string $hqId): bool;

    public function lockTenantIdentity(PricingResource $resource, string $identityId, ?string $hqId): ?PricingIdentityRecordInterface;

    /** @return list<string> Version ids of one identity, newest first. */
    public function versionIdsNewestFirst(PricingResource $resource, string $identityId): array;

    public function lockTenantVersion(PricingResource $resource, string $versionId, ?string $hqId): ?PricingVersionRecordInterface;

    public function lockLatestVersionOf(PricingResource $resource, string $identityId): ?PricingVersionRecordInterface;

    /** @param list<string> $statuses */
    public function hasVersionWithStatus(PricingResource $resource, string $identityId, array $statuses): bool;

    /** Whether another approved or published successor already covers the period a version claims. */
    public function hasOverlappingEffectiveVersion(PricingResource $resource, PricingVersionPeriodDto $period): bool;

    /** Copies a version onto a fresh draft. @param array<string, mixed> $overrides */
    public function replicateAsDraft(PricingResource $resource, PricingVersionRecordInterface $previous, array $overrides): string;
}
