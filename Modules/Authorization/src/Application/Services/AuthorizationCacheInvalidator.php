<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Services;

final readonly class AuthorizationCacheInvalidator
{
    public function __construct(
        private \Modules\Authorization\Application\Contracts\AuthorizationCache $cache,
        private \Modules\Authorization\Application\Repositories\AuthorizationRepository $repository,
    )
    {
    }

    public function invalidateUser(string $userId): void
    {
        $this->cache->forget($this->cacheKey($userId));
    }

    public function invalidateTenant(string $hqId): void
    {
        array_map(fn($userId) => $this->invalidateUser((string) $userId), $this->repository->tenantUserIds($hqId));
    }

    public function invalidateRoleUsers(string $roleId): void
    {
        array_map(fn($userId) => $this->invalidateUser((string) $userId), $this->repository->activeRoleUserIds($roleId));
    }

    public function cacheKey(string $userId): string
    {
        return "chabok:authz:effective:{$userId}";
    }
}
