<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface CustomerAccessGuardInterface
{
    /** Returns the tenant ID for which the actor may create customers. */
    public function assertCanCreate(AuthenticatedPrincipal $actor): string;

    /** Returns the tenant ID whose customers the actor may read, whether as a list or one by one. */
    public function assertCanRead(AuthenticatedPrincipal $actor): string;

    /** Returns the tenant ID whose customer profiles the actor may edit. */
    public function assertCanEdit(AuthenticatedPrincipal $actor): string;

    /**
     * Returns the tenant ID whose customer finances the actor may read. Credit and settlement figures sit
     * behind their own permission, so seeing a customer file is not by itself seeing its money.
     */
    public function assertCanReadFinance(AuthenticatedPrincipal $actor): string;

    /** Returns the tenant ID whose customer finances the actor may write. */
    public function assertCanEditFinance(AuthenticatedPrincipal $actor): string;
}
