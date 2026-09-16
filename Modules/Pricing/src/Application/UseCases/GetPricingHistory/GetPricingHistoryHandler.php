<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\GetPricingHistory;

use Carbon\CarbonImmutable;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class GetPricingHistoryHandler
{
    public function __construct(
        private \Modules\Pricing\Application\Services\PricingAccessGuard $pricingAccessGuard,
        private \Modules\Pricing\Application\Services\PricingVersionGuard $pricingVersionGuard,
        private \Modules\Pricing\Application\Repositories\PricingRepository $pricing,
        private \Modules\Pricing\Application\Services\PricingReader $pricingReader,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Pricing\Application\Services\PricingZoneResolver $pricingZoneResolver,
    )
    {
    }

    public function handle(GetPricingHistoryCommand $command): GetPricingHistoryResult
    {
        return new GetPricingHistoryResult($this->execute($command->actor, $command->kind, $command->identityId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $kind, string $identityId): array
    {
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.view');
        [$parentId, $versionId] = $this->pricingVersionGuard->pricingMap($kind);
        if (!$this->pricing->identityVisible($actor->hqId, $kind, $identityId)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        return array_map(function ($id) use ($kind, $actor): array {
            if ($kind !== 'tariffs') {
                return $this->pricingReader->zoneVersion($actor, (string) $id);
            }
            $version = $this->pricingReader->tariffVersion($actor, (string) $id);
            $version['configured_zone_set'] = $version['zone_set_version_id'] ? $this->pricingReader->zoneVersion($actor, $version['zone_set_version_id']) : null;
            if (!$version['zone_set_version_id']) {
                return $version;
            }
            $asOf = CarbonImmutable::instance($this->clock->now())->utc();
            $version['zone_resolution_at'] = $asOf->toISOString();
            $version['effective_zone_set'] = null;
            $version['zone_resolution_error'] = null;
            try {
                $effectiveId = $this->pricingZoneResolver->resolveEffectiveZoneSetVersion($version['zone_set_version_id'], $asOf);
                $version['effective_zone_set'] = $this->pricingReader->zoneVersion($actor, $effectiveId);
            } catch (ApiException $error) {
                $version['zone_resolution_error'] = 'PRICING_ZONE_VERSION_UNAVAILABLE_OR_AMBIGUOUS';
            }
            return $version;
        }, $this->pricing->history($kind, $identityId));
    }
}
