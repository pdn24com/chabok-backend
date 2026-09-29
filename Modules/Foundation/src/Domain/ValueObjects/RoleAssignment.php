<?php

declare(strict_types=1);

namespace Modules\Foundation\Domain\ValueObjects;

final readonly class RoleAssignment
{
    public function __construct(public string $roleId, public PermissionScope $scope) {}
}
