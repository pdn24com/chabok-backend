<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\UpdateRole;

use Illuminate\Database\ConnectionInterface;
use Modules\Authorization\Application\Contracts\AuthorizationCacheInvalidatorInterface;
use Modules\Authorization\Application\Contracts\AuthorizationGuardInterface;
use Modules\Authorization\Application\Contracts\RoleNavigationInterface;
use Modules\Authorization\Application\Contracts\RolePermissionWriterInterface;
use Modules\Authorization\Application\Contracts\RoleReaderInterface;
use Modules\Authorization\Application\Repositories\RolePermissionRepositoryInterface;
use Modules\Authorization\Application\Repositories\RoleRepositoryInterface;
use Modules\Authorization\Application\Serialization\RoleDocument;
use Modules\Authorization\Infrastructure\Persistence\Models\RoleRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;

final readonly class UpdateRoleHandler
{
    public function __construct(
        private AuthorizationGuardInterface $authorizationGuard,
        private ConnectionInterface $connection,
        private RoleReaderInterface $roleReader,
        private RoleNavigationInterface $roleNavigation,
        private RolePermissionWriterInterface $rolePermissionWriter,
        private ClockInterface $clock,
        private AuthorizationCacheInvalidatorInterface $authorizationCacheInvalidator,
        private AuditWriterInterface $auditWriter,
        private OutboxWriterInterface $outboxWriter,
        private RoleRepositoryInterface $roleRepository,
        private RolePermissionRepositoryInterface $rolePermissionRepository,
    ) {}

    public function handle(UpdateRoleCommand $command): RoleRecord
    {
        $actor = $command->actor;
        $roleId = $command->roleId;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $hqId = $this->authorizationGuard->tenantId($actor);
        $this->authorizationGuard->assertRoleManagement($actor);

        return $this->connection->transaction(function () use ($actor, $roleId, $input, $correlationId, $hqId): RoleRecord {
            $role = $this->roleRepository->lock($roleId);
            $this->authorizationGuard->assertMutableRole($role, $hqId);
            $before = RoleDocument::serialize($this->roleReader->role($roleId));
            $permissionCodes = $input->permissionCodes;
            if ($input->menuSpecified) {
                $this->roleNavigation->replace($roleId, $input->menuKeys);
            }
            $this->authorizationGuard->assertDelegablePermissions($actor, $permissionCodes ?? $before['permission_codes'], true);
            if ($permissionCodes !== null) {
                $this->rolePermissionRepository->deleteForRole($roleId);
                $this->rolePermissionWriter->insertRolePermissions($roleId, $permissionCodes, $actor->userId);
            }
            $this->roleRepository->apply($role, [...$input->attributes(), 'updated_at' => $this->clock->now()]);
            $after = $this->roleReader->role($roleId);
            $this->authorizationCacheInvalidator->invalidateRoleUsers($roleId);
            $this->auditWriter->write($hqId, $actor->userId, 'ROLE_UPDATED', 'ROLE', $roleId, $correlationId, $before, RoleDocument::serialize($after));
            $this->outboxWriter->write($hqId, 'ROLE', $roleId, 'iam.role.updated', $correlationId, ['role_id' => $roleId]);

            return $after;
        }, attempts: 3);
    }
}
