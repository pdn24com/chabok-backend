<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Application\UseCases\CreateOpportunity;

use Modules\CrmOpportunitie\Application\Dto\OpportunityDraftDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class CreateOpportunityCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public OpportunityDraftDto $input) {}
}
