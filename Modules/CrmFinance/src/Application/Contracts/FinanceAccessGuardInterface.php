<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface FinanceAccessGuardInterface
{
    /** Returns the tenant ID whose customer finances the actor may read. */
    public function assertCanRead(AuthenticatedPrincipal $actor): string;

    /** Returns the tenant ID whose customer finances the actor may write. */
    public function assertCanEdit(AuthenticatedPrincipal $actor): string;
}
