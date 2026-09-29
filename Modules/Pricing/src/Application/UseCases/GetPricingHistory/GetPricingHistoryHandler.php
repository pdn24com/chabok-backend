<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\GetPricingHistory;

use Carbon\CarbonImmutable;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Pricing\Application\Contracts\PricingAccessGuardInterface;
use Modules\Pricing\Application\Contracts\PricingReaderInterface;
use Modules\Pricing\Application\Dto\TariffHistoryEntryDto;
use Modules\Pricing\Application\Repositories\PricingVersionRepositoryInterface;
use Modules\Pricing\Application\Repositories\PricingZoneRepositoryInterface;
use Modules\Pricing\Domain\Enums\PricingResource;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneSetVersionRecord;

final readonly class GetPricingHistoryHandler
{
    public function __construct(
        private PricingAccessGuardInterface $pricingAccessGuard,
        private PricingReaderInterface $pricingReader,
        private ClockInterface $clock,
        private PricingVersionRepositoryInterface $pricingVersionRepository,
        private PricingZoneRepositoryInterface $pricingZoneRepository,
    ) {}

    /** @return list<TariffHistoryEntryDto|PricingZoneSetVersionRecord> */
    public function handle(GetPricingHistoryCommand $command): array
    {
        $actor = $command->actor;
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.view');
        $resource = $command->kind;
        if (! $this->pricingVersionRepository->identityVisible($resource, $command->identityId, $actor->hqId)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }
        $ids = $this->pricingVersionRepository->versionIdsNewestFirst($resource, $command->identityId);
        if ($command->kind === PricingResource::ZoneSets) {
            $versions = $this->pricingReader->zoneVersions($actor, $ids)->keyBy('zone_set_version_id');

            return array_map(static fn (string $id) => $versions->get($id), $ids);
        }
        $versions = $this->pricingReader->tariffVersions($actor, $ids)->keyBy('tariff_version_id');
        $configuredIds = $versions->pluck('zone_set_version_id')->filter()->unique()->values()->all();
        $configured = $this->pricingReader->zoneVersions($actor, $configuredIds)->keyBy('zone_set_version_id');
        $asOf = CarbonImmutable::instance($this->clock->now())->utc();
        $effectiveIds = [];
        // Select current group successors once, then eager-load all documents together.
        $candidates = $this->pricingZoneRepository->publishedVersionsOfZoneSets($configured->pluck('pricing_zone_set_id')->all(), $asOf);
        foreach ($candidates as $candidate) {
            $effectiveIds[$candidate->pricing_zone_set_id] ??= $candidate->zone_set_version_id;
        }
        $effective = $this->pricingReader->zoneVersions($actor, array_values($effectiveIds))->keyBy('zone_set_version_id');
        $entries = [];
        foreach ($ids as $id) {
            $version = $versions->get($id);
            $configuredZoneSet = $configured->get($version->zone_set_version_id);
            if ($version->zone_set_version_id !== null && $configuredZoneSet === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            $currentId = $configuredZoneSet === null ? null : ($effectiveIds[$configuredZoneSet->pricing_zone_set_id] ?? null);
            $entries[] = new TariffHistoryEntryDto($version, $configuredZoneSet, $effective->get($currentId), $configuredZoneSet === null ? null : $asOf);
        }

        return $entries;
    }
}
