<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ListPricingZoneSets;

use Modules\Foundation\Application\Data\Page;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListPricingZoneSetsHandler
{
    public function __construct(
        private \Modules\Pricing\Application\Services\PricingAccessGuard $pricingAccessGuard,
        private \Modules\Pricing\Application\Repositories\PricingRepository $pricing,
    )
    {
    }

    public function handle(ListPricingZoneSetsCommand $command): ListPricingZoneSetsResult
    {
        return new ListPricingZoneSetsResult($this->execute($command->actor, $command->filters));
    }

    private function execute(AuthenticatedPrincipal $actor, array $filters): Page
    {
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.view');
        return $this->pricing->listZoneSets($actor->hqId, $filters);
    }
}
