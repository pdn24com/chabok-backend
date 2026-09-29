<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Authorization\Infrastructure\Persistence\Models\RoleRecord;

interface RoleRepositoryInterface
{
    public function find(string $roleId): ?RoleRecord;

    public function lock(string $roleId): ?RoleRecord;

    /** Loads a Role with everything a Role response renders, so no lazy load happens during serialization. */
    public function findWithGrants(string $roleId): ?RoleRecord;

    /** Platform roles and the tenant's own roles, in the order the administration screen lists them. @return Collection<int, RoleRecord> */
    public function visibleWithGrants(?string $hqId): Collection;

    /** @param list<string> $roleIds @return Collection<string, RoleRecord> */
    public function activeWithPermissionsKeyedById(array $roleIds): Collection;

    public function isAssignable(string $roleId): bool;

    /** @param array<string, mixed> $attributes */
    public function insert(array $attributes): string;

    /** @param array<string, mixed> $changes */
    public function apply(RoleRecord $role, array $changes): void;
}
