<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Application\UseCases\ListOpportunityBoard;

use Modules\CrmOpportunitie\Application\Contracts\OpportunityAccessGuardInterface;
use Modules\CrmOpportunitie\Application\Repositories\FunnelStepRepositoryInterface;
use Modules\CrmOpportunitie\Application\Repositories\OpportunityRepositoryInterface;
use Modules\CrmOpportunitie\Application\Repositories\SalesFunnelRepositoryInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class ListOpportunityBoardHandler
{
    public function __construct(
        private OpportunityAccessGuardInterface $accessGuard,
        private SalesFunnelRepositoryInterface $salesFunnelRepository,
        private FunnelStepRepositoryInterface $funnelStepRepository,
        private OpportunityRepositoryInterface $opportunityRepository,
    ) {}

    public function handle(ListOpportunityBoardCommand $command): ListOpportunityBoardResult
    {
        $hqId = $this->accessGuard->assertCanRead($command->actor);
        // A funnel of another tenant is indistinguishable from one that does not exist.
        $funnel = $this->salesFunnelRepository->findForTenant($hqId, $command->filters->funnelId)
            ?? throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');

        $steps = $this->funnelStepRepository->activeForFunnel($hqId, $command->filters->funnelId);
        $grouped = [];
        foreach ($this->opportunityRepository->listForBoard($hqId, $command->filters) as $opportunity) {
            $grouped[(string) $opportunity->current_step_id][] = $opportunity;
        }

        // The columns are the active steps and nothing else. An opportunity left standing on a step that
        // was retired since has no column to sit in and is not drawn; moving it forward brings it back.
        $columns = [];
        foreach ($steps as $step) {
            $stepId = (string) $step->sales_funnel_step_id;
            $columns[$stepId] = $grouped[$stepId] ?? [];
        }

        return new ListOpportunityBoardResult($funnel, $steps, $columns);
    }
}
