<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\CreateRole;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Authorization\Domain\AuthorizationWriteConflict;

final readonly class CreateRoleHandler
{
    public function __construct(
        private \Modules\Authorization\Application\Services\AuthorizationGuard $authorizationGuard,
        private \Modules\Foundation\Application\Contracts\TransactionManager $transactions,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Authorization\Application\Repositories\AuthorizationRepository $repository,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
        private \Modules\Authorization\Application\Services\RolePermissionWriter $rolePermissionWriter,
        private \Modules\Authorization\Application\RoleNavigation $navigation,
        private \Modules\Authorization\Application\Services\RoleReader $roleReader,
        private \Modules\Foundation\Application\Contracts\AuditWriter $audit,
        private \Modules\Foundation\Application\Contracts\OutboxWriter $outbox,
    )
    {
    }

    public function handle(CreateRoleCommand $command): CreateRoleResult
    {
        return new CreateRoleResult($this->execute($command->actor, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        $hqId = $this->authorizationGuard->tenantId($actor);
        $this->authorizationGuard->assertRoleManagement($actor);
        return $this->transactions->run(function () use ($actor, $input, $correlationId, $hqId): array {
            $this->authorizationGuard->assertDelegablePermissions($actor, $input['permission_codes'], true);
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
            $this->rolePermissionWriter->insertRolePermissions($roleId, $input['permission_codes'], $actor->userId);
            $this->navigation->replace($roleId, $input['menu_keys'] ?? null);
            $payload = $this->roleReader->rolePayload($roleId);
            $this->audit->write($hqId, $actor->userId, 'ROLE_CREATED', 'ROLE', $roleId, $correlationId, after: $payload);
            $this->outbox->write($hqId, 'ROLE', $roleId, 'iam.role.created', $correlationId, ['role_id' => $roleId]);
            return $payload;
        });
    }
}
