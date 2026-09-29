<?php

declare(strict_types=1);

namespace Modules\CrmTeam\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface TeamAccessGuardInterface
{
    /** Returns the tenant ID whose teams and memberships the actor may read. */
    public function assertCanRead(AuthenticatedPrincipal $actor): string;

    /** Returns the tenant ID whose teams and memberships the actor may change. */
    public function assertCanManage(AuthenticatedPrincipal $actor): string;
}
