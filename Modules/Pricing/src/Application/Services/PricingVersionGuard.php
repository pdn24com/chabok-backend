<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Pricing\Application\Contracts\PricingVersionGuardInterface;
use Modules\Pricing\Application\Dto\PricingVersionPeriodDto;
use Modules\Pricing\Application\Repositories\PricingVersionRepositoryInterface;
use Modules\Pricing\Application\Repositories\PricingZoneRepositoryInterface;
use Modules\Pricing\Application\Repositories\TariffRepositoryInterface;
use Modules\Pricing\Domain\Enums\PricingResource;
use Modules\Pricing\Domain\Policies\PricingVersionPolicy;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneSetVersionRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffVersionRecord;

final readonly class PricingVersionGuard implements PricingVersionGuardInterface
{
    public function __construct(
        private TariffRepositoryInterface $tariffRepository,
        private PricingZoneRepositoryInterface $pricingZoneRepository,
        private PricingVersionRepositoryInterface $pricingVersionRepository,
    ) {}

    public function assertDraft(TariffVersionRecord|PricingZoneSetVersionRecord|null $row, int $expected): void
    {
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }
        PricingVersionPolicy::assertDraft($row->status, (int) $row->lock_version, $expected);
    }

    public function hasVersionOverlap(PricingResource $kind, PricingVersionPeriodDto $period): bool
    {
        if ($period->validFrom === null) {
            return false;
        }

        return $this->pricingVersionRepository->hasOverlappingEffectiveVersion($kind, $period);
    }

    public function defaultConflict(TariffVersionRecord $version, string $hqId): bool
    {
        if ($version->family->tariff_kind !== 'FREIGHT') {
            return true;
        }
        $offeringIds = $this->tariffRepository->offeringIdsOfVersionRules((string) $version->tariff_version_id);

        return $this->tariffRepository->hasCompetingDefault($hqId, (string) $version->tariff_family_id, $offeringIds,
            $version->valid_from, $version->valid_to);
    }
}
