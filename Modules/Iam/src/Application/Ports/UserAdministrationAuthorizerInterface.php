<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Ports;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

/**
 * Port owned by Iam, implemented by the Authorization module.
 *
 * @see Modules/Authorization/src/Infrastructure/Adapters/AuthorizationUserAdministrationAuthorizer.php (bound in AuthorizationServiceProvider)
 */
interface UserAdministrationAuthorizerInterface
{
    public function assertCan(
        AuthenticatedPrincipal $actor,
        string $permission,
        string $hqId,
    ): void;
}
