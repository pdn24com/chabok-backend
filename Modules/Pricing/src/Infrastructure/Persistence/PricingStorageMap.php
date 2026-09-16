<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Persistence;

final class PricingStorageMap
{
    public static function map(string $kind): array
    {
        return match ($kind) {
            'tariffs' => ['tariff_families', 'tariff_versions', 'tariff_family_id', 'tariff_version_id'],
            'zone-sets' => ['pricing_zone_sets', 'pricing_zone_set_versions', 'pricing_zone_set_id', 'zone_set_version_id'],
            default => throw new \InvalidArgumentException('Unknown pricing resource.'),
        };
    }

    public static function query(string $table): \Illuminate\Database\Query\Builder
    {
        $model = match ($table) {
            'pricing_charge_lines' => Models\PricingChargeLineRecord::class,
            'pricing_charge_types' => Models\PricingChargeTypeRecord::class,
            'pricing_quote_lines' => Models\PricingQuoteLineRecord::class,
            'pricing_quotes' => Models\PricingQuoteRecord::class,
            'pricing_snapshots' => Models\PricingSnapshotRecord::class,
            'pricing_zone_members' => Models\PricingZoneMemberRecord::class,
            'pricing_zones' => Models\PricingZoneRecord::class,
            'pricing_zone_sets' => Models\PricingZoneSetRecord::class,
            'pricing_zone_set_versions' => Models\PricingZoneSetVersionRecord::class,
            'tariff_families' => Models\TariffFamilyRecord::class,
            'tariff_rate_rules' => Models\TariffRateRuleRecord::class,
            'tariff_service_attachments' => Models\TariffServiceAttachmentRecord::class,
            'tariff_versions' => Models\TariffVersionRecord::class,
            default => throw new \InvalidArgumentException('Unknown pricing storage.'),
        };
        return $model::query()->toBase();
    }
}
