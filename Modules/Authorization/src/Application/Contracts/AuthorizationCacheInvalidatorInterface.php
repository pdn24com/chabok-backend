<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Contracts;

interface AuthorizationCacheInvalidatorInterface
{
    public function invalidateUser(string $userId): void;

    public function invalidateTenant(string $hqId): void;

    public function invalidateRoleUsers(string $roleId): void;

    public function cacheKey(string $userId): string;
}
