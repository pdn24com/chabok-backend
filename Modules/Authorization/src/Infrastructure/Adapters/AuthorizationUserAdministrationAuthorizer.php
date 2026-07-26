<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Adapters;

use Modules\Authorization\Application\AuthorizationService;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\User\Application\Contracts\UserAdministrationAuthorizer;

final readonly class AuthorizationUserAdministrationAuthorizer implements UserAdministrationAuthorizer
{
    public function __construct(private AuthorizationService $authorization) {}

    public function assertCan(
        AuthenticatedPrincipal $actor,
        string $permission,
        string $hqId,
    ): void {
        $this->authorization->assertPermission($actor, $permission, $hqId);
    }
}
