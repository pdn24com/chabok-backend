<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ListServiceTariffReferences;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListServiceTariffReferencesHandler
{
    public function __construct(
        private \Modules\Pricing\Application\Services\PricingAccessGuard $pricingAccessGuard,
        private \Modules\Pricing\Application\Repositories\PricingRepository $pricing,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
    )
    {
    }

    public function handle(ListServiceTariffReferencesCommand $command): ListServiceTariffReferencesResult
    {
        return new ListServiceTariffReferencesResult($this->execute($command->actor));
    }

    private function execute(AuthenticatedPrincipal $actor): array
    {
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.view');
        return array_map(fn($r) => (array) $r, $this->pricing->serviceTariffReferences($actor->hqId, $this->clock->now()));
    }
}
