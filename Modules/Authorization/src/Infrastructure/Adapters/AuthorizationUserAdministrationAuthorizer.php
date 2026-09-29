<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Adapters;

use Modules\Authorization\Application\Contracts\AuthorizationGuardInterface;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Iam\Application\Ports\UserAdministrationAuthorizerInterface;

final readonly class AuthorizationUserAdministrationAuthorizer implements UserAdministrationAuthorizerInterface
{
    public function __construct(private AuthorizationGuardInterface $authorizationGuard) {}

    public function assertCan(
        AuthenticatedPrincipal $actor,
        string $permission,
        string $hqId,
    ): void {
        $this->authorizationGuard->assertPermission($actor, $permission, $hqId);
    }
}
