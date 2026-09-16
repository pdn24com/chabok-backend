<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Repositories;

interface RoleNavigationRepository
{
    /** @return list<string>|null */

    public function selection(string $roleId): ?array;
    /** @param list<string>|null $keys */

    public function replace(string $roleId, ?array $keys, \DateTimeImmutable $at): void;
    /** @param list<string> $roleIds */

    public function configuredRoleCount(array $roleIds): int;
    /** @param list<string> $roleIds @return list<string> */

    public function selectedKeys(array $roleIds): array;
}
