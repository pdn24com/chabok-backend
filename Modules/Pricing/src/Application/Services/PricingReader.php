<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class PricingReader
{
    public function __construct(
        private \Modules\Pricing\Application\Repositories\PricingRepository $pricing,
        private \Modules\Pricing\Application\ServiceTariffDependencies $serviceTariffs,
        private \Modules\Pricing\Application\Services\PricingAccessGuard $pricingAccessGuard,
    )
    {
    }

    public function tariffVersion(AuthenticatedPrincipal $actor, string $versionId): array
    {
        $row = $this->pricing->tariffVersion($actor->hqId, $versionId);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        $result = $this->decode((array) $row);
        $result['service_tariff_family_ids'] = $this->serviceTariffs->ids($versionId);
        $result['rules'] = array_map(fn($r) => $this->decode((array) $r), $this->pricing->orderedRules($versionId));
        return $result;
    }

    public function zoneVersion(AuthenticatedPrincipal $actor, string $versionId): array
    {
        $row = $this->pricing->zoneVersion($actor->hqId, $versionId);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        $result = (array) $row;
        $result['zones'] = array_map(function ($z) {
            $zone = (array) $z;
            $zone['members'] = array_map(fn($m) => [
                ...(array) $m,
                'geometry' => $m->geometry === null ? null : json_decode($m->geometry, true, 512, JSON_THROW_ON_ERROR),
            ], $this->pricing->zoneMembers($z->pricing_zone_id));
            return $zone;
        }, $this->pricing->zones($versionId));
        return $result;
    }

    public function snapshotDetail(string $snapshotId): array
    {
        $snapshot = (array) $this->pricing->snapshot($snapshotId);
        $snapshot['lines'] = array_map(fn($row) => $this->decode((array) $row), $this->pricing->chargeLines($snapshotId));
        return $snapshot;
    }

    public function quoteDetail(AuthenticatedPrincipal $actor, string $quoteId): array
    {
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.quote.view', runtime: true);
        $row = $this->pricing->quote($actor->hqId, $quoteId);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        $result = $this->decode((array) $row);
        $result['lines'] = array_map(fn($r) => $this->decode((array) $r), $this->pricing->quoteLinesWithCategory($quoteId));
        return $result;
    }

    public function decode(array $row): array
    {
        foreach ([
            'freight_matrices',
            'normalized_input',
            'resolution_evidence',
            'warnings',
            'explanation',
            'conditions',
            'basis_charge_codes',
        ] as $field) {
            if (isset($row[$field]) && is_string($row[$field])) {
                $row[$field] = json_decode($row[$field], true);
            }
        }
        return $row;
    }
}
