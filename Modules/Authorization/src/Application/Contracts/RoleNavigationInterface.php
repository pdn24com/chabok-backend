<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Contracts;

interface RoleNavigationInterface
{
    /** Null retains automatic permission-based navigation. @return list<string>|null */
    public function forRole(string $roleId): ?array;

    /** Caller owns the role transaction and authorization. @param list<string>|null $keys */
    public function replace(string $roleId, ?array $keys): void;

    /** @param list<string> $roleIds @return list<string>|null */
    public function effective(array $roleIds): ?array;
}
