<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Services;

use Illuminate\Contracts\Cache\Repository;
use Modules\Authorization\Application\Contracts\AuthorizationCacheInvalidatorInterface;
use Modules\Authorization\Application\Repositories\AssignmentRepositoryInterface;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;

final readonly class AuthorizationCacheInvalidator implements AuthorizationCacheInvalidatorInterface
{
    public function __construct(
        private Repository $authorizationCache,
        private UserRepositoryInterface $userRepository,
        private AssignmentRepositoryInterface $assignmentRepository,
    ) {}

    public function invalidateUser(string $userId): void
    {
        $this->authorizationCache->forget($this->cacheKey($userId));
    }

    public function invalidateTenant(string $hqId): void
    {
        array_map(fn ($userId) => $this->invalidateUser((string) $userId), $this->userRepository->idsByTenant($hqId));
    }

    public function invalidateRoleUsers(string $roleId): void
    {
        array_map(fn ($userId) => $this->invalidateUser((string) $userId), $this->assignmentRepository->activeUserIdsForRole($roleId));
    }

    public function cacheKey(string $userId): string
    {
        return "chabok:authz:effective:{$userId}";
    }
}
