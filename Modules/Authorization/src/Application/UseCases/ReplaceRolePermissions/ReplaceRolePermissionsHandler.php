<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\ReplaceRolePermissions;

use Illuminate\Database\ConnectionInterface;
use Modules\Authorization\Application\Contracts\AuthorizationCacheInvalidatorInterface;
use Modules\Authorization\Application\Contracts\AuthorizationGuardInterface;
use Modules\Authorization\Application\Contracts\RolePermissionWriterInterface;
use Modules\Authorization\Application\Contracts\RoleReaderInterface;
use Modules\Authorization\Application\Repositories\RolePermissionRepositoryInterface;
use Modules\Authorization\Application\Repositories\RoleRepositoryInterface;
use Modules\Authorization\Application\Serialization\RoleDocument;
use Modules\Authorization\Infrastructure\Persistence\Models\RoleRecord;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;

final readonly class ReplaceRolePermissionsHandler
{
    public function __construct(
        private AuthorizationGuardInterface $authorizationGuard,
        private ConnectionInterface $connection,
        private RoleReaderInterface $roleReader,
        private RolePermissionWriterInterface $rolePermissionWriter,
        private AuthorizationCacheInvalidatorInterface $authorizationCacheInvalidator,
        private AuditWriterInterface $auditWriter,
        private OutboxWriterInterface $outboxWriter,
        private RoleRepositoryInterface $roleRepository,
        private RolePermissionRepositoryInterface $rolePermissionRepository,
    ) {}

    public function handle(ReplaceRolePermissionsCommand $command): RoleRecord
    {
        $actor = $command->actor;
        $roleId = $command->roleId;
        $permissionCodes = $command->permissionCodes;
        $correlationId = $command->correlationId;
        $hqId = $this->authorizationGuard->tenantId($actor);
        $this->authorizationGuard->assertRoleManagement($actor);
        $this->authorizationGuard->assertDelegablePermissions($actor, $permissionCodes, true);

        return $this->connection->transaction(function () use ($actor, $roleId, $permissionCodes, $correlationId, $hqId): RoleRecord {
            $role = $this->roleRepository->lock($roleId);
            $this->authorizationGuard->assertMutableRole($role, $hqId);
            $before = RoleDocument::serialize($this->roleReader->role($roleId));
            $this->rolePermissionRepository->deleteForRole($roleId);
            $this->rolePermissionWriter->insertRolePermissions($roleId, $permissionCodes, $actor->userId);
            $after = $this->roleReader->role($roleId);
            $this->authorizationCacheInvalidator->invalidateRoleUsers($roleId);
            $this->auditWriter->write($hqId, $actor->userId, 'ROLE_PERMISSIONS_REPLACED', 'ROLE', $roleId, $correlationId, $before, RoleDocument::serialize($after));
            $this->outboxWriter->write($hqId, 'ROLE', $roleId, 'iam.role.permissions_replaced', $correlationId, ['role_id' => $roleId]);

            return $after;
        }, attempts: 3);
    }
}
