<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Application\UseCases\ListOpportunityBoard;

use Modules\CrmOpportunitie\Application\Dto\OpportunityBoardFiltersDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListOpportunityBoardCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public OpportunityBoardFiltersDto $filters) {}
}
