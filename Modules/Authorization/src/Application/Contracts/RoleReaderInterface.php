<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Modules\Authorization\Infrastructure\Persistence\Models\RoleRecord;

interface RoleReaderInterface
{
    public function role(string $roleId): RoleRecord;

    public function visibleRoles(?string $hqId): Collection;

    public function permissionCodesForRole(string $roleId): array;
}
