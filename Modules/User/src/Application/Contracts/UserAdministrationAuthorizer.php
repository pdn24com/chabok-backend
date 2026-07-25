<?php

declare(strict_types=1);

namespace Modules\User\Application\Contracts;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

interface UserAdministrationAuthorizer
{
    public function assertCan(
        AuthenticatedPrincipal $actor,
        string $permission,
        string $hqId,
    ): void;
}
