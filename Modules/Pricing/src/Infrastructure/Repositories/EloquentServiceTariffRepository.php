<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Repositories;

use Carbon\CarbonImmutable;
use Modules\Pricing\Application\Repositories\ServiceTariffRepository;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneSetVersionRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingChargeTypeRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffFamilyRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffVersionRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffServiceAttachmentRecord;

final class EloquentServiceTariffRepository implements ServiceTariffRepository
{
    public function attachedFamilyIds(string $versionId): array
    {
        return TariffServiceAttachmentRecord::query()->toBase()->where('tariff_version_id', $versionId)->pluck('service_tariff_family_id')->all();
    }

    public function deleteAttachments(string $versionId): void
    {
        TariffServiceAttachmentRecord::query()->toBase()->where('tariff_version_id', $versionId)->delete();
    }

    public function attachFamily(string $versionId, string $id): void
    {
        TariffServiceAttachmentRecord::query()->toBase()->insert(['tariff_version_id' => $versionId, 'service_tariff_family_id' => $id]);
    }

    public function zoneGroup(string $versionId): ?string
    {
        return PricingZoneSetVersionRecord::query()->toBase()->where('zone_set_version_id', $versionId)->value('pricing_zone_set_id');
    }

    public function chargeCode(string $chargeId): ?string
    {
        return PricingChargeTypeRecord::query()->toBase()->where('charge_type_id', $chargeId)->value('code');
    }

    public function serviceFamily(string $hqId, string $id): ?object
    {
        return TariffFamilyRecord::query()->toBase()->where('tariff_family_id', $id)->where('tariff_kind', 'SERVICE')->where(fn($q) => $q->whereNull('hq_id')->orWhere('hq_id', $hqId))->first();
    }

    public function publishedVersion(string $id, \DateTimeInterface $asOf): ?object
    {
        return TariffVersionRecord::query()->toBase()->where('tariff_family_id', $id)->where('status', 'PUBLISHED')->where('valid_from', '<=', CarbonImmutable::instance($asOf)->utc()->format('Y-m-d H:i:s.u'))->where(fn($q) => $q->whereNull('valid_to')->orWhere('valid_to', '>', CarbonImmutable::instance($asOf)->utc()->format('Y-m-d H:i:s.u')))->orderByDesc('version_number')->first();
    }

    public function hasIncompatibleParents(string $familyId, ?string $group, \DateTimeInterface $at): bool
    {
        $parents = TariffServiceAttachmentRecord::query()->toBase()->from('tariff_service_attachments as a')->join('tariff_versions as v', 'v.tariff_version_id', '=', 'a.tariff_version_id')->join('pricing_zone_set_versions as z', 'z.zone_set_version_id', '=', 'v.zone_set_version_id')->where('a.service_tariff_family_id', $familyId)->whereIn('v.status', ['APPROVED', 'PUBLISHED'])->where('z.pricing_zone_set_id', '!=', $group)->where(fn($q) => $q->whereNull('v.valid_to')->orWhere('v.valid_to', '>', $at));
        return $parents->exists();
    }
}
