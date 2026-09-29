<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ListPricingChargeTypes;

use Modules\Pricing\Application\Contracts\PricingAccessGuardInterface;
use Modules\Pricing\Application\Repositories\PricingChargeTypeRepositoryInterface;

final readonly class ListPricingChargeTypesHandler
{
    public function __construct(
        private PricingAccessGuardInterface $pricingAccessGuard,
        private PricingChargeTypeRepositoryInterface $pricingChargeTypeRepository,
    ) {}

    public function handle(ListPricingChargeTypesCommand $command): array
    {
        $actor = $command->actor;
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.view');

        return array_map(static fn ($chargeType): array => $chargeType->attributesToArray(), $this->pricingChargeTypeRepository->catalogue());
    }
}
