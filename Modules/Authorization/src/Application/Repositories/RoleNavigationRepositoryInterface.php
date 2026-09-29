<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Repositories;

use DateTimeInterface;

interface RoleNavigationRepositoryInterface
{
    public function hasPreference(string $roleId): bool;

    /** @param list<string> $roleIds */
    public function preferenceCount(array $roleIds): int;

    /** @param list<string> $roleIds @return list<string> */
    public function selectedKeys(array $roleIds): array;

    public function deleteItems(string $roleId): void;

    public function deletePreference(string $roleId): void;

    public function touchPreference(string $roleId, DateTimeInterface $at): void;

    /** @param list<string> $menuKeys */
    public function insertItems(string $roleId, array $menuKeys): void;
}
