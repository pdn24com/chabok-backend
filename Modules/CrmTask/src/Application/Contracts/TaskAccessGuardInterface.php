<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface TaskAccessGuardInterface
{
    /** Returns the tenant ID whose tasks the actor may read. */
    public function assertCanRead(AuthenticatedPrincipal $actor): string;

    /** Returns the tenant ID whose tasks the actor may raise, move, act on and finish. */
    public function assertCanManage(AuthenticatedPrincipal $actor): string;

    /** Returns the tenant ID whose customers, opportunities and tasks the actor may record interactions against. */
    public function assertCanRecordActivity(AuthenticatedPrincipal $actor): string;
}
