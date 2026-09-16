<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class OfferingReferenceGuard
{
    public function __construct(private \Modules\ServiceCatalog\Application\Repositories\CatalogRepository $catalog)
    {
    }

    public function assertOfferingReferences(array $input, ?string $hqId): void
    {
        foreach ((array) ($input['availability_bindings'] ?? []) as $binding) {
            $scopeType = (string) ($binding['scope_type'] ?? '');
            if (!in_array($scopeType, ['PLATFORM', 'TENANT', 'CHANNEL'], true)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'The selected availability scope has no authoritative reference directory.');
            }
            if ($scopeType === 'CHANNEL' && !in_array((string) ($binding['scope_value'] ?? ''), ['BRANCH', 'VENDOR', 'DRIVER', 'HQ', 'API', 'TRACKING', 'LEGACY'], true)) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'The availability channel is invalid.');
            }
        }
        foreach ((array) ($input['coverage_references'] ?? []) as $reference) {
            $type = (string) ($reference['reference_type'] ?? '');
            $value = (string) ($reference['reference_value'] ?? '');
            $valid = match ($type) {
                'COUNTRY' => $value === 'IR',
                'PROVINCE' => $this->catalog->activeProvince($value),
                'CITY' => $this->catalog->activeCity($value),
                'OPERATIONAL_AREA' => $hqId !== null && $this->catalog->tenantArea($hqId, $value),
                'PRICING_ZONE_SET' => $this->catalog->visibleZoneSet($hqId, $value),
                'POSTAL_RANGE' => preg_match('/^\d{10}$/', $value) === 1 && preg_match('/^\d{10}$/', (string) ($reference['secondary_reference_value'] ?? '')) === 1 && strcmp($value, (string) $reference['secondary_reference_value']) <= 0,
                default => false,
            };
            if (!$valid) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'The coverage reference is invalid or outside the current tenant.');
            }
        }
    }
}
