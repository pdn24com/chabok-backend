<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface ContractAccessGuardInterface
{
    /** Returns the tenant ID whose customer contracts the actor may read. */
    public function assertCanRead(AuthenticatedPrincipal $actor): string;

    /** Returns the tenant ID whose customer contracts the actor may record. */
    public function assertCanEdit(AuthenticatedPrincipal $actor): string;
}
