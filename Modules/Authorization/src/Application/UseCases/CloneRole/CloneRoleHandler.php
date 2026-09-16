<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\CloneRole;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Authorization\Domain\AuthorizationWriteConflict;

final readonly class CloneRoleHandler
{
    public function __construct(
        private \Modules\Authorization\Application\Services\AuthorizationGuard $authorizationGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Authorization\Application\Repositories\AuthorizationRepository $repository,
        private \Modules\Authorization\Application\Services\RoleReader $roleReader,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Authorization\Application\Services\RolePermissionWriter $rolePermissionWriter,
        private \Modules\Authorization\Application\RoleNavigation $navigation,
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
        private \Modules\Foundation\Application\Contracts\OutboxWriter $outbox,
    )
    {
    }

    public function handle(CloneRoleCommand $command): CloneRoleResult
    {
        return new CloneRoleResult($this->execute($command->actor, $command->sourceRoleId, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $sourceRoleId, array $input, string $correlationId): array
    {
        $hqId = $this->authorizationGuard->tenantId($actor);
        $this->authorizationGuard->assertRoleManagement($actor);
        return $this->transactions->run(function () use ($actor, $sourceRoleId, $input, $correlationId, $hqId): array {
            $source = $this->repository->lockedRole($sourceRoleId);
            if ($source === null || !(bool) $source->is_cloneable || $source->status !== 'ACTIVE') {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'The role cannot be cloned.');
            }
            if ($source->hq_id !== null && $source->hq_id !== $hqId) {
                throw new ApiException(ApiErrorCode::TenantAccessDenied, 403, 'Access denied.');
            }
            $sourcePermissions = $input['permission_codes'] ?? $this->roleReader->permissionCodesForRole($sourceRoleId);
            $this->authorizationGuard->assertDelegablePermissions($actor, $sourcePermissions, true);
            $roleId = $this->identifiers->uuid();
            try {
                $this->repository->insertRole([
                    'role_id' => $roleId,
                    'hq_id' => $hqId,
                    'owner_key' => $hqId,
                    'role_code' => $input['role_code'],
                    'role_title' => $input['role_title'],
                    'description' => $input['description'] ?? null,
                    'role_kind' => 'CUSTOM',
                    'is_cloneable' => true,
                    'status' => 'ACTIVE',
                    'created_by' => $actor->userId,
                    'created_at' => $this->clock->now(),
                    'updated_at' => $this->clock->now(),
                ]);
            } catch (AuthorizationWriteConflict $exception) {
                throw new ApiException(ApiErrorCode::Conflict, 409, 'The role code is already in use.');
            }
            $this->rolePermissionWriter->insertRolePermissions($roleId, $sourcePermissions, $actor->userId);
            $this->navigation->replace($roleId, array_key_exists('menu_keys', $input) ? $input['menu_keys'] : $this->navigation->forRole($sourceRoleId));
            $payload = $this->roleReader->rolePayload($roleId);
            $this->audit->write($hqId, $actor->userId, 'ROLE_CLONED', 'ROLE', $roleId, $correlationId, after: $payload);
            $this->outbox->write($hqId, 'ROLE', $roleId, 'iam.role.cloned', $correlationId, ['role_id' => $roleId, 'source_role_id' => $sourceRoleId]);
            return $payload;
        });
    }
}
