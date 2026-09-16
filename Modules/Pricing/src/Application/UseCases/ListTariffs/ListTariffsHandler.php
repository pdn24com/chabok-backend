<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ListTariffs;

use Modules\Foundation\Application\Data\Page;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListTariffsHandler
{
    public function __construct(
        private \Modules\Pricing\Application\Services\PricingAccessGuard $pricingAccessGuard,
        private \Modules\Pricing\Application\Repositories\PricingRepository $pricing,
    )
    {
    }

    public function handle(ListTariffsCommand $command): ListTariffsResult
    {
        return new ListTariffsResult($this->execute($command->actor, $command->filters));
    }

    private function execute(AuthenticatedPrincipal $actor, array $filters): Page
    {
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.view');
        return $this->pricing->listTariffs($actor->hqId, $filters);
    }
}
