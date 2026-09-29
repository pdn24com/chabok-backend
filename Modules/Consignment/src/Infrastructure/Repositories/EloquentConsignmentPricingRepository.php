<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Repositories;

use Modules\Consignment\Application\Repositories\ConsignmentPricingRepositoryInterface;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentPricingChargeLineRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingQuoteRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneMemberRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleVersionRecord;

final class EloquentConsignmentPricingRepository implements ConsignmentPricingRepositoryInterface
{
    /** Zone versions whose members still describe a price a live Consignment may have been quoted against. */
    private const PRICED_ZONE_STATUSES = ['PUBLISHED', 'SUPERSEDED'];

    public function insertChargeLines(array $rows): void
    {
        ConsignmentPricingChargeLineRecord::query()->insert($rows);
    }

    public function findQuoteForActiveSnapshot(ConsignmentRecord $consignment): ?PricingQuoteRecord
    {
        return PricingQuoteRecord::query()
            ->whereHas('snapshots', fn ($snapshot) => $snapshot->where(['hq_id' => $consignment->hq_id, 'pricing_snapshot_id' => $consignment->active_pricing_snapshot_id]))
            ->with('zoneVersion')->first();
    }

    public function publishedSchedulePolicy(?string $commitmentScheduleVersionId): ?array
    {
        $scheduleId = CommitmentScheduleVersionRecord::query()
            ->where('commitment_schedule_version_id', $commitmentScheduleVersionId)->value('commitment_schedule_id');

        return CommitmentScheduleVersionRecord::query()->where('commitment_schedule_id', $scheduleId)
            ->where('status', 'PUBLISHED')->orderByDesc('version_number')->value('commitment_policy');
    }

    public function publishedZoneMemberTypes(?string $hqId, array $zoneSetIds): array
    {
        return PricingZoneMemberRecord::query()->whereHas('zone.version', fn ($version) => $version
            ->whereIn('pricing_zone_set_id', $zoneSetIds)
            ->where(fn ($scope) => $scope->where('hq_id', $hqId)->orWhereNull('hq_id'))
            ->whereIn('status', self::PRICED_ZONE_STATUSES))->distinct()->pluck('member_type')->all();
    }
}
