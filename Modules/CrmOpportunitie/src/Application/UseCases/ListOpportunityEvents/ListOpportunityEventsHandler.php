<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Application\UseCases\ListOpportunityEvents;

use Modules\CrmOpportunitie\Application\Contracts\OpportunityAccessGuardInterface;
use Modules\CrmOpportunitie\Application\Repositories\OpportunityEventRepositoryInterface;
use Modules\CrmOpportunitie\Application\Repositories\OpportunityRepositoryInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class ListOpportunityEventsHandler
{
    public function __construct(
        private OpportunityAccessGuardInterface $accessGuard,
        private OpportunityRepositoryInterface $opportunityRepository,
        private OpportunityEventRepositoryInterface $opportunityEventRepository,
    ) {}

    public function handle(ListOpportunityEventsCommand $command): ListOpportunityEventsResult
    {
        $hqId = $this->accessGuard->assertCanRead($command->actor);
        // An opportunity of another tenant is indistinguishable from one that does not exist.
        if (! $this->opportunityRepository->existsForTenant($hqId, $command->opportunityId)) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return new ListOpportunityEventsResult($this->opportunityEventRepository->listForOpportunity($hqId, $command->opportunityId));
    }
}
