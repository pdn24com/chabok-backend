<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\ReplaceRolePermissions;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ReplaceRolePermissionsHandler
{
    public function __construct(
        private \Modules\Authorization\Application\Services\AuthorizationGuard $authorizationGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Authorization\Application\Repositories\AuthorizationRepository $repository,
        private \Modules\Authorization\Application\Services\RoleReader $roleReader,
        private \Modules\Authorization\Application\Services\RolePermissionWriter $rolePermissionWriter,
        private \Modules\Authorization\Application\Services\AuthorizationCacheInvalidator $authorizationCacheInvalidator,
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
        private \Modules\Foundation\Application\Contracts\OutboxWriter $outbox,
    )
    {
    }

    public function handle(ReplaceRolePermissionsCommand $command): ReplaceRolePermissionsResult
    {
        return new ReplaceRolePermissionsResult($this->execute($command->actor, $command->roleId, $command->permissionCodes, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $roleId, array $permissionCodes, string $correlationId): array
    {
        $hqId = $this->authorizationGuard->tenantId($actor);
        $this->authorizationGuard->assertRoleManagement($actor);
        $this->authorizationGuard->assertDelegablePermissions($actor, $permissionCodes, true);
        return $this->transactions->run(function () use ($actor, $roleId, $permissionCodes, $correlationId, $hqId): array {
            $role = $this->repository->lockedRole($roleId);
            $this->authorizationGuard->assertMutableRole($role, $hqId);
            $before = $this->roleReader->rolePayload($roleId);
            $this->repository->removeRolePermissions($roleId);
            $this->rolePermissionWriter->insertRolePermissions($roleId, $permissionCodes, $actor->userId);
            $after = $this->roleReader->rolePayload($roleId);
            $this->authorizationCacheInvalidator->invalidateRoleUsers($roleId);
            $this->audit->write($hqId, $actor->userId, 'ROLE_PERMISSIONS_REPLACED', 'ROLE', $roleId, $correlationId, $before, $after);
            $this->outbox->write($hqId, 'ROLE', $roleId, 'iam.role.permissions_replaced', $correlationId, ['role_id' => $roleId]);
            return $after;
        });
    }
}
