<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\UpdateRole;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class UpdateRoleHandler
{
    public function __construct(
        private \Modules\Authorization\Application\Services\AuthorizationGuard $authorizationGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Authorization\Application\Repositories\AuthorizationRepository $repository,
        private \Modules\Authorization\Application\Services\RoleReader $roleReader,
        private \Modules\Authorization\Application\RoleNavigation $navigation,
        private \Modules\Authorization\Application\Services\RolePermissionWriter $rolePermissionWriter,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Authorization\Application\Services\AuthorizationCacheInvalidator $authorizationCacheInvalidator,
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
        private \Modules\Foundation\Application\Contracts\OutboxWriter $outbox,
    )
    {
    }

    public function handle(UpdateRoleCommand $command): UpdateRoleResult
    {
        return new UpdateRoleResult($this->execute($command->actor, $command->roleId, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, string $roleId, array $input, string $correlationId): array
    {
        $hqId = $this->authorizationGuard->tenantId($actor);
        $this->authorizationGuard->assertRoleManagement($actor);
        return $this->transactions->run(function () use ($actor, $roleId, $input, $correlationId, $hqId): array {
            $role = $this->repository->lockedRole($roleId);
            $this->authorizationGuard->assertMutableRole($role, $hqId);
            $before = $this->roleReader->rolePayload($roleId);
            $permissionCodes = $input['permission_codes'] ?? null;
            if (array_key_exists('menu_keys', $input)) {
                $this->navigation->replace($roleId, $input['menu_keys']);
                unset($input['menu_keys']);
            }
            unset($input['permission_codes']);
            $this->authorizationGuard->assertDelegablePermissions($actor, $permissionCodes ?? $before['permission_codes'], true);
            if ($permissionCodes !== null) {
                $this->repository->removeRolePermissions($roleId);
                $this->rolePermissionWriter->insertRolePermissions($roleId, $permissionCodes, $actor->userId);
            }
            $this->repository->updateRole($roleId, [...$input, 'updated_at' => $this->clock->now()]);
            $after = $this->roleReader->rolePayload($roleId);
            $this->authorizationCacheInvalidator->invalidateRoleUsers($roleId);
            $this->audit->write($hqId, $actor->userId, 'ROLE_UPDATED', 'ROLE', $roleId, $correlationId, $before, $after);
            $this->outbox->write($hqId, 'ROLE', $roleId, 'iam.role.updated', $correlationId, ['role_id' => $roleId]);
            return $after;
        });
    }
}
