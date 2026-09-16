<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ListPricingAuditEvents;

use Modules\Foundation\Application\Data\Page;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListPricingAuditEventsHandler
{
    public function __construct(
        private \Modules\Pricing\Application\Services\PricingAccessGuard $pricingAccessGuard,
        private \Modules\Pricing\Application\Repositories\PricingRepository $pricing,
    )
    {
    }

    public function handle(ListPricingAuditEventsCommand $command): ListPricingAuditEventsResult
    {
        return new ListPricingAuditEventsResult($this->execute($command->actor, $command->filters));
    }

    private function execute(AuthenticatedPrincipal $actor, array $filters): Page
    {
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.audit.view');
        return $this->pricing->auditEvents($actor->hqId, $filters);
    }
}
