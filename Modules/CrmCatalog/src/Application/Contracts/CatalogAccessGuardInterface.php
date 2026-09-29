<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface CatalogAccessGuardInterface
{
    /** Returns the tenant ID whose catalog the actor may read. */
    public function assertCanRead(AuthenticatedPrincipal $actor): string;

    /** Returns the tenant ID whose catalog the actor may change. */
    public function assertCanManage(AuthenticatedPrincipal $actor): string;
}
