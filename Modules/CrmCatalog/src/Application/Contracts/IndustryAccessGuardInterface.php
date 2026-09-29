<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface IndustryAccessGuardInterface
{
    /** Returns the tenant ID whose industry knowledge base the actor may list. */
    public function assertCanList(AuthenticatedPrincipal $actor): string;
}
