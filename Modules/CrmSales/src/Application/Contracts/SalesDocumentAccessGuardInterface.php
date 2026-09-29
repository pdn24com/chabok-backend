<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface SalesDocumentAccessGuardInterface
{
    /** Returns the tenant ID whose sales documents the actor may read. */
    public function assertCanRead(AuthenticatedPrincipal $actor): string;

    /** Returns the tenant ID whose sales documents the actor may draw up, issue and close. */
    public function assertCanEdit(AuthenticatedPrincipal $actor): string;
}
