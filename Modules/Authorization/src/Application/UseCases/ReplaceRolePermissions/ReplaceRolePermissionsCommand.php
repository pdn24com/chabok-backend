<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\ReplaceRolePermissions;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ReplaceRolePermissionsCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $roleId,
        public array $permissionCodes,
        public string $correlationId,
    )
    {
    }
}
