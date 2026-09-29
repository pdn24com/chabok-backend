<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface OpportunityAccessGuardInterface
{
    /** Returns the tenant ID whose funnels and opportunities the actor may read. */
    public function assertCanRead(AuthenticatedPrincipal $actor): string;

    /** Returns the tenant ID whose opportunities the actor may open and move. */
    public function assertCanEdit(AuthenticatedPrincipal $actor): string;
}
