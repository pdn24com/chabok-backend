<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ListServiceTariffReferences;

use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Pricing\Application\Contracts\PricingAccessGuardInterface;
use Modules\Pricing\Application\Repositories\TariffRepositoryInterface;

final readonly class ListServiceTariffReferencesHandler
{
    public function __construct(
        private PricingAccessGuardInterface $pricingAccessGuard,
        private ClockInterface $clock,
        private TariffRepositoryInterface $tariffRepository,
    ) {}

    public function handle(ListServiceTariffReferencesCommand $command): array
    {
        $actor = $command->actor;
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.view');

        $at = $this->clock->now();
        $families = $this->tariffRepository->referenceableServiceFamilies($actor->hqId, $at);
        $references = [];
        foreach ($families as $family) {
            $version = $family->versions->first();
            $references[] = ['tariff_family_id' => $family->tariff_family_id, 'title' => $family->title,
                'code' => $family->code, 'service_charge_type_id' => $family->service_charge_type_id,
                'charge_code' => $family->chargeType->code, 'tariff_version_id' => $version->tariff_version_id,
                'version_number' => $version->version_number, 'pricing_zone_set_id' => $version->zoneVersion?->pricing_zone_set_id];
        }
        usort($references, static fn (array $left, array $right): int => $right['version_number'] <=> $left['version_number']);

        return $references;
    }
}
