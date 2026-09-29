<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Ports;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

/**
 * Port owned by Iam, implemented by the Authorization module.
 *
 * @see Modules/Authorization/src/Infrastructure/Adapters/AuthorizationUserScopeAuthorizer.php (bound in AuthorizationServiceProvider)
 */
interface UserScopeAuthorizerInterface
{
    public function visibleUserIds(AuthenticatedPrincipal $actor, ?string $nodeId = null): ?array;

    public function assertTarget(
        AuthenticatedPrincipal $actor,
        string $userId,
        string $permission,
    ): void;
}
