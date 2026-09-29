<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Application\UseCases\ListSalesFunnels;

use Modules\CrmOpportunitie\Application\Contracts\OpportunityAccessGuardInterface;
use Modules\CrmOpportunitie\Application\Repositories\SalesFunnelRepositoryInterface;

final readonly class ListSalesFunnelsHandler
{
    public function __construct(
        private OpportunityAccessGuardInterface $accessGuard,
        private SalesFunnelRepositoryInterface $salesFunnelRepository,
    ) {}

    public function handle(ListSalesFunnelsCommand $command): ListSalesFunnelsResult
    {
        $hqId = $this->accessGuard->assertCanRead($command->actor);

        return new ListSalesFunnelsResult($this->salesFunnelRepository->listForTenant($hqId, $command->filters->active));
    }
}
