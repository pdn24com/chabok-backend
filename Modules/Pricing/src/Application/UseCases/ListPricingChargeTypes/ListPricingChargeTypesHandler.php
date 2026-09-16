<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ListPricingChargeTypes;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListPricingChargeTypesHandler
{
    public function __construct(
        private \Modules\Pricing\Application\Services\PricingAccessGuard $pricingAccessGuard,
        private \Modules\Pricing\Application\Repositories\PricingRepository $pricing,
    )
    {
    }

    public function handle(ListPricingChargeTypesCommand $command): ListPricingChargeTypesResult
    {
        return new ListPricingChargeTypesResult($this->execute($command->actor));
    }

    private function execute(AuthenticatedPrincipal $actor): array
    {
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.view');
        return array_map(fn($row) => (array) $row, $this->pricing->chargeTypes());
    }
}
