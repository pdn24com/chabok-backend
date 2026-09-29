<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Application\UseCases\ListOpportunityEvents;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListOpportunityEventsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $opportunityId) {}
}
