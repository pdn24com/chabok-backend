<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Illuminate\Database\Eloquent\Collection;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Pricing\Application\Contracts\PricingAccessGuardInterface;
use Modules\Pricing\Application\Contracts\PricingReaderInterface;
use Modules\Pricing\Application\Repositories\PricingQuoteRepositoryInterface;
use Modules\Pricing\Application\Repositories\PricingZoneRepositoryInterface;
use Modules\Pricing\Application\Repositories\TariffRepositoryInterface;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingQuoteRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingSnapshotRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneSetVersionRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffVersionRecord;

final readonly class PricingReader implements PricingReaderInterface
{
    public function __construct(
        private PricingAccessGuardInterface $pricingAccessGuard,
        private TariffRepositoryInterface $tariffRepository,
        private PricingZoneRepositoryInterface $pricingZoneRepository,
        private PricingQuoteRepositoryInterface $pricingQuoteRepository,
    ) {}

    public function tariffVersion(AuthenticatedPrincipal $actor, string $versionId): TariffVersionRecord
    {
        $version = $this->tariffVersions($actor, [$versionId])->first();
        if ($version === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $version;
    }

    public function zoneVersion(AuthenticatedPrincipal $actor, string $versionId): PricingZoneSetVersionRecord
    {
        $version = $this->zoneVersions($actor, [$versionId])->first();
        if ($version === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $version;
    }

    /** @param list<string> $ids @return Collection<int, TariffVersionRecord> */
    public function tariffVersions(AuthenticatedPrincipal $actor, array $ids): Collection
    {
        return $this->tariffRepository->visibleVersionDetails($actor->hqId, $ids);
    }

    /** @param list<string> $ids @return Collection<int, PricingZoneSetVersionRecord> */
    public function zoneVersions(AuthenticatedPrincipal $actor, array $ids): Collection
    {
        return $this->pricingZoneRepository->visibleVersionDetails($actor->hqId, $ids);
    }

    public function snapshotDetail(string $snapshotId): PricingSnapshotRecord
    {
        $snapshot = $this->pricingQuoteRepository->snapshotWithLines($snapshotId);

        return $snapshot;
    }

    public function quoteDetail(AuthenticatedPrincipal $actor, string $quoteId): PricingQuoteRecord
    {
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.quote.view', runtime: true);
        $row = $this->pricingQuoteRepository->findTenantQuoteWithLines($actor->hqId, $quoteId);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $row;
    }
}
