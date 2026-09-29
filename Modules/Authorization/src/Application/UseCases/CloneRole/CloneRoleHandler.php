<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\CloneRole;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\QueryException;
use Modules\Authorization\Application\Contracts\AuthorizationGuardInterface;
use Modules\Authorization\Application\Contracts\RoleNavigationInterface;
use Modules\Authorization\Application\Contracts\RolePermissionWriterInterface;
use Modules\Authorization\Application\Contracts\RoleReaderInterface;
use Modules\Authorization\Application\Repositories\RoleRepositoryInterface;
use Modules\Authorization\Application\Serialization\RoleDocument;
use Modules\Authorization\Infrastructure\Persistence\Models\RoleRecord;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;

final readonly class CloneRoleHandler
{
    public function __construct(
        private AuthorizationGuardInterface $authorizationGuard,
        private ConnectionInterface $connection,
        private RoleReaderInterface $roleReader,
        private ClockInterface $clock,
        private RolePermissionWriterInterface $rolePermissionWriter,
        private RoleNavigationInterface $roleNavigation,
        private AuditWriterInterface $auditWriter,
        private OutboxWriterInterface $outboxWriter,
        private RoleRepositoryInterface $roleRepository,
    ) {}

    public function handle(CloneRoleCommand $command): RoleRecord
    {
        $actor = $command->actor;
        $sourceRoleId = $command->sourceRoleId;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $hqId = $this->authorizationGuard->tenantId($actor);
        $this->authorizationGuard->assertRoleManagement($actor);

        return $this->connection->transaction(function () use ($actor, $sourceRoleId, $input, $correlationId, $hqId): RoleRecord {
            $source = $this->roleRepository->lock($sourceRoleId);
            if ($source === null || ! (bool) $source->is_cloneable || $source->status !== 'ACTIVE') {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'authorization.role_cannot_be_cloned');
            }
            if ($source->hq_id !== null && $source->hq_id !== $hqId) {
                throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'common.access_denied');
            }
            $sourcePermissions = $input->permissionCodes ?? $this->roleReader->permissionCodesForRole($sourceRoleId);
            $this->authorizationGuard->assertDelegablePermissions($actor, $sourcePermissions, true);
            try {
                $roleId = $this->roleRepository->insert([

                    'hq_id' => $hqId,
                    'owner_key' => $hqId,
                    'role_code' => $input->code,
                    'role_title' => $input->title,
                    'description' => $input->description,
                    'role_kind' => 'CUSTOM',
                    'is_cloneable' => true,
                    'status' => 'ACTIVE',
                    'created_by' => $actor->userId,
                    'created_at' => $this->clock->now(),
                    'updated_at' => $this->clock->now(),
                ]);
            } catch (QueryException $exception) {
                if ($exception->getCode() !== '23000') {
                    throw $exception;
                }
                throw new ApiException(ApiErrorCode::Conflict, 409, 'authorization.role_code_is_already_use');
            }
            $this->rolePermissionWriter->insertRolePermissions($roleId, $sourcePermissions, $actor->userId);
            $this->roleNavigation->replace($roleId, $input->menuSpecified ? $input->menuKeys : $this->roleNavigation->forRole($sourceRoleId));
            $payload = $this->roleReader->role($roleId);
            $this->auditWriter->write($hqId, $actor->userId, 'ROLE_CLONED', 'ROLE', $roleId, $correlationId, after: RoleDocument::serialize($payload));
            $this->outboxWriter->write($hqId, 'ROLE', $roleId, 'iam.role.cloned', $correlationId, ['role_id' => $roleId, 'source_role_id' => $sourceRoleId]);

            return $payload;
        }, attempts: 3);
    }
}
