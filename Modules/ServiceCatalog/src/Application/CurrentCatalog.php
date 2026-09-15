<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

/** Resolve legacy revision references through their immutable stable identity. */
final class CurrentCatalog
{
    public const MAP = [
        'service-types' => ['service_types', 'service_type_versions', 'service_type_id', 'service_type_version_id'],
        'shipping-methods' => ['shipping_methods', 'shipping_method_versions', 'shipping_method_id', 'shipping_method_version_id'],
        'offerings' => ['service_offerings', 'service_offering_versions', 'service_offering_id', 'service_offering_version_id'],
        'options' => ['service_options', 'service_option_versions', 'service_option_id', 'service_option_version_id'],
        'commitment-schedules' => ['commitment_schedules', 'commitment_schedule_versions', 'commitment_schedule_id', 'commitment_schedule_version_id'],
    ];

    public static function resolve(string $resource, string $reference, ?string $hqId = null, bool $locking = false): array
    {
        [$identities, $versions, $identityId, $versionId] = self::MAP[$resource];
        $stableId = DB::table($versions)->where($versionId, $reference)->value($identityId) ?? $reference;
        $row = DB::table("{$versions} as v")->join("{$identities} as i", "i.{$identityId}", '=', "v.{$identityId}")
            ->where("i.{$identityId}", $stableId)->where('i.status', 'ACTIVE')->where('v.status', 'PUBLISHED')
            ->when($hqId !== null, fn ($q) => $q->where(fn ($scope) => $scope->whereNull('i.hq_id')->orWhere('i.hq_id', $hqId)))
            ->orderByDesc('v.version_number')->when($locking, fn ($q) => $q->lockForUpdate())->select(['v.*', 'i.code'])->first();
        if ($row === null) throw new ApiException(ApiErrorCode::ValidationError, 422, 'The catalog dependency is inactive or unavailable.', details: ['reason_code' => 'CATALOG_DEPENDENCY_UNAVAILABLE', 'resource' => $resource]);
        return (array) $row;
    }

    public static function relatedVersions(string $resource, string $reference): array
    {
        [, $versions, $identityId, $versionId] = self::MAP[$resource];
        $id = DB::table($versions)->where($versionId, $reference)->value($identityId) ?? $reference;
        return DB::table($versions)->where($identityId, $id)->pluck($versionId)->all();
    }

    public static function optionBound(string $offeringReference, string $optionReference, string $hqId): bool
    {
        try {
            $offering = self::resolve('offerings', $offeringReference, $hqId);
            $option = self::resolve('options', $optionReference, $hqId);
            return DB::table('service_offering_option_rules')->where('service_offering_version_id', $offering['service_offering_version_id'])
                ->whereIn('service_option_version_id', self::relatedVersions('options', $option['service_option_id']))->exists();
        } catch (ApiException) {
            return false;
        }
    }

    /** An unissued quote cannot pin obsolete catalog settings after an edit. */
    public static function assertQuoteCurrent(object $quote): void
    {
        $evidence = json_decode((string) $quote->resolution_evidence, true)['service'] ?? [];
        $zone=$evidence['commitment']['destination_zone']??null;
        if($zone) {
            $current=app(\Modules\ServiceCatalog\Application\Contracts\CommitmentZoneResolver::class)->group((string)$quote->hq_id,$zone['zone_set_id'],true);
            if($current['zone_set_version_id']!==$zone['zone_set_version_id']) throw new ApiException(ApiErrorCode::PricingQuoteMismatch,422,'گروه زون تعهد تغییر کرده؛ استعلام جدید بگیرید.',details:['reason_code'=>'CATALOG_CHANGED']);
        }
        $references = ['offerings' => (string) $quote->service_offering_version_id];
        foreach (['service_type_version_id' => 'service-types', 'shipping_method_version_id' => 'shipping-methods'] as $field => $resource)
            if (!empty($evidence[$field])) $references[$resource] = $evidence[$field];
        if (!empty($evidence['commitment']['schedule_version_id'])) $references['commitment-schedules'] = $evidence['commitment']['schedule_version_id'];
        foreach ($references as $resource => $reference) self::assertReferenceCurrent($resource, $reference, (string) $quote->hq_id);
        foreach ($evidence['selected_services'] ?? [] as $option) self::assertReferenceCurrent('options', $option['service_option_version_id'], (string) $quote->hq_id);
    }

    private static function assertReferenceCurrent(string $resource, string $reference, string $hqId): void
    {
        [$table, $versions, $identityId, $versionId] = self::MAP[$resource];
        $id = DB::table($versions)->where($versionId, $reference)->value($identityId);
        DB::table($table)->where($identityId, $id)->lockForUpdate()->first();
        if (self::resolve($resource, $reference, $hqId, true)[$versionId] !== $reference)
            throw new ApiException(ApiErrorCode::PricingQuoteMismatch, 422, 'Catalog settings changed. Calculate a new quote.', details: ['reason_code' => 'CATALOG_CHANGED']);
    }

    public static function fingerprint(array $input): string
    {
        unset($input['expected_version'], $input['code']);
        $sort = function (array $value) use (&$sort): array {
            if (!array_is_list($value)) ksort($value);
            foreach ($value as &$item) if (is_array($item)) $item = $sort($item);
            return $value;
        };
        return hash('sha256', json_encode($sort($input), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
