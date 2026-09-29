<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Repositories;

use Modules\Authorization\Infrastructure\Persistence\Models\PermissionRecord;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface PermissionRepositoryInterface
{
    public function findActiveByCode(string $permissionCode): ?PermissionRecord;

    /** @param list<string> $permissionCodes @return list<PermissionRecord> */
    public function activeByCodes(array $permissionCodes): array;

    /** @return list<PermissionRecord> */
    public function catalogue(?string $moduleCode): array;

    /** @return list<string> */
    public function codesForRole(string $roleId): array;

    /**
     * Permission codes the actor may delegate: granted through one of their active assignments and,
     * for a tenant-wide grant, limited to modules the tenant still has enabled.
     *
     * @return list<string>
     */
    public function delegableCodes(AuthenticatedPrincipal $actor, bool $tenantWide): array;
}
