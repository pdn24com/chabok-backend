<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application;

use Modules\ServiceCatalog\Application\Repositories\CatalogIdentityRepository;
use Modules\ServiceCatalog\Application\Contracts\CommitmentZoneResolver;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
/** Resolve legacy revision references through their immutable stable identity. */

final readonly class CurrentCatalog implements Contracts\CatalogResolver
{
    public function __construct(private CatalogIdentityRepository $identities, private CommitmentZoneResolver $zones)
    {
    }

    public function resolve(string $resource, string $reference, ?string $hqId = null, bool $locking = false): array
    {
        $row = $this->identities->current($resource, $reference, $hqId, $locking);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The catalog dependency is inactive or unavailable.', details: ['reason_code' => 'CATALOG_DEPENDENCY_UNAVAILABLE', 'resource' => $resource]);
        }
        return (array) $row;
    }

    public function relatedVersions(string $resource, string $reference): array
    {
        return $this->identities->relatedVersions($resource, $reference);
    }

    public function optionBound(string $offeringReference, string $optionReference, string $hqId): bool
    {
        try {
            $offering = $this->resolve('offerings', $offeringReference, $hqId);
            $option = $this->resolve('options', $optionReference, $hqId);
            return $this->identities->optionBound($offering['service_offering_version_id'], $this->relatedVersions('options', $option['service_option_id']));
        } catch (ApiException) {
            return false;
        }
    }
    /** An unissued quote cannot pin obsolete catalog settings after an edit. */

    public function assertQuoteCurrent(object $quote): void
    {
        $evidence = json_decode((string) $quote->resolution_evidence, true)['service'] ?? [];
        $zone = $evidence['commitment']['destination_zone'] ?? null;
        if ($zone) {
            $current = $this->zones->group((string) $quote->hq_id, $zone['zone_set_id'], true);
            if ($current['zone_set_version_id'] !== $zone['zone_set_version_id']) {
                throw new ApiException(ApiErrorCode::PricingQuoteMismatch, 422, 'گروه زون تعهد تغییر کرده؛ استعلام جدید بگیرید.', details: ['reason_code' => 'CATALOG_CHANGED']);
            }
        }
        $references = ['offerings' => (string) $quote->service_offering_version_id];
        foreach (['service_type_version_id' => 'service-types', 'shipping_method_version_id' => 'shipping-methods'] as $field => $resource) {
            if (!empty($evidence[$field])) {
                $references[$resource] = $evidence[$field];
            }
        }
        if (!empty($evidence['commitment']['schedule_version_id'])) {
            $references['commitment-schedules'] = $evidence['commitment']['schedule_version_id'];
        }
        foreach ($references as $resource => $reference) {
            $this->assertReferenceCurrent($resource, $reference, (string) $quote->hq_id);
        }
        foreach ($evidence['selected_services'] ?? [] as $option) {
            $this->assertReferenceCurrent('options', $option['service_option_version_id'], (string) $quote->hq_id);
        }
    }

    private function assertReferenceCurrent(string $resource, string $reference, string $hqId): void
    {
        $versionId = $this->identities->revisionKey($resource);
        $this->identities->lockIdentityForRevision($resource, $reference);
        if ($this->resolve($resource, $reference, $hqId, true)[$versionId] !== $reference) {
            throw new ApiException(ApiErrorCode::PricingQuoteMismatch, 422, 'Catalog settings changed. Calculate a new quote.', details: ['reason_code' => 'CATALOG_CHANGED']);
        }
    }

    public static function fingerprint(array $input): string
    {
        unset($input['expected_version'], $input['code']);
        $sort = function (array $value) use (&$sort): array {
            if (!array_is_list($value)) {
                ksort($value);
            }
            foreach ($value as &$item) {
                if (is_array($item)) {
                    $item = $sort($item);
                }
            }
            return $value;
        };
        return hash('sha256', json_encode($sort($input), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }
}
