<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ListPricingZoneVersionReferences;

use Modules\Foundation\Application\Data\Page;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListPricingZoneVersionReferencesHandler
{
    public function __construct(
        private \Modules\Pricing\Application\Services\PricingAccessGuard $pricingAccessGuard,
        private \Modules\Pricing\Application\Repositories\PricingRepository $pricing,
    )
    {
    }

    public function handle(ListPricingZoneVersionReferencesCommand $command): ListPricingZoneVersionReferencesResult
    {
        return new ListPricingZoneVersionReferencesResult($this->execute($command->actor, $command->filters));
    }

    private function execute(AuthenticatedPrincipal $actor, array $filters): Page
    {
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.view');
        return $this->pricing->listZoneSetVersionReferences($actor->hqId, $filters);
    }
}
