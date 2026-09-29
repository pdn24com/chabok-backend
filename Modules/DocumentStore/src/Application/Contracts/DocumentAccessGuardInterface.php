<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface DocumentAccessGuardInterface
{
    /** Returns the tenant ID whose documents the actor may read. */
    public function assertCanRead(AuthenticatedPrincipal $actor): string;

    /** Returns the tenant ID whose documents the actor may register, change and attach. */
    public function assertCanEdit(AuthenticatedPrincipal $actor): string;
}
