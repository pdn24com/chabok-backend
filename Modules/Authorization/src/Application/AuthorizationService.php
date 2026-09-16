<?php

declare(strict_types=1);

namespace Modules\Authorization\Application;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class AuthorizationService implements \Modules\Foundation\Application\Contracts\AuthorizationContextResolver
{
    public function __construct(
        private \Modules\Authorization\Application\UseCases\ResolveContext\ResolveContextHandler $resolveContext,
        private \Modules\Authorization\Application\Services\AuthorizationGuard $authorizationGuard,
        private \Modules\Authorization\Application\UseCases\ListAccessibleNodes\ListAccessibleNodesHandler $listAccessibleNodes,
        private \Modules\Authorization\Application\UseCases\ListRoles\ListRolesHandler $listRoles,
        private \Modules\Authorization\Application\UseCases\GetRole\GetRoleHandler $getRole,
        private \Modules\Authorization\Application\UseCases\UpdateRole\UpdateRoleHandler $updateRole,
        private \Modules\Authorization\Application\UseCases\CreateRole\CreateRoleHandler $createRole,
        private \Modules\Authorization\Application\UseCases\CloneRole\CloneRoleHandler $cloneRole,
        private \Modules\Authorization\Application\UseCases\ReplaceRolePermissions\ReplaceRolePermissionsHandler $replaceRolePermissions,
        private \Modules\Authorization\Application\UseCases\ListPermissions\ListPermissionsHandler $listPermissions,
        private \Modules\Authorization\Application\UseCases\CreateAssignments\CreateAssignmentsHandler $createAssignments,
        private \Modules\Authorization\Application\UseCases\UpdateAssignment\UpdateAssignmentHandler $updateAssignment,
        private \Modules\Authorization\Application\UseCases\RevokeAssignment\RevokeAssignmentHandler $revokeAssignment,
        private \Modules\Authorization\Application\UseCases\ListEntitlements\ListEntitlementsHandler $listEntitlements,
        private \Modules\Authorization\Application\UseCases\AssignInitialRoles\AssignInitialRolesHandler $assignInitialRoles,
        private \Modules\Authorization\Application\Services\AuthorizationCacheInvalidator $authorizationCacheInvalidator,
        private \Modules\Authorization\Application\UseCases\GetAssignmentOptions\GetAssignmentOptionsHandler $getAssignmentOptions,
    )
    {
    }

    public function resolve(AuthenticatedPrincipal $principal): array
    {
        return $this->resolveContext->handle(new \Modules\Authorization\Application\UseCases\ResolveContext\ResolveContextCommand($principal))->data;
    }

    public function assertPermission(AuthenticatedPrincipal $actor, string $permissionCode, ?string $expectedHqId = null): void
    {
        $this->authorizationGuard->assertPermission($actor, $permissionCode, $expectedHqId);
    }

    public function accessibleNodes(AuthenticatedPrincipal $actor): array
    {
        return $this->listAccessibleNodes->handle(new \Modules\Authorization\Application\UseCases\ListAccessibleNodes\ListAccessibleNodesCommand($actor))->data;
    }

    public function assertNodeAccessible(AuthenticatedPrincipal $actor, string $nodeId): void
    {
        $this->authorizationGuard->assertNodeAccessible($actor, $nodeId);
    }

    public function hasActivePlatformAssignment(string $userId): bool
    {
        return $this->authorizationGuard->hasActivePlatformAssignment($userId);
    }

    public function listRoles(AuthenticatedPrincipal $actor): array
    {
        return $this->listRoles->handle(new \Modules\Authorization\Application\UseCases\ListRoles\ListRolesCommand($actor))->data;
    }

    public function getRole(AuthenticatedPrincipal $actor, string $roleId): array
    {
        return $this->getRole->handle(new \Modules\Authorization\Application\UseCases\GetRole\GetRoleCommand($actor, $roleId))->data;
    }

    public function updateRole(AuthenticatedPrincipal $actor, string $roleId, array $input, string $correlationId): array
    {
        return $this->updateRole->handle(new \Modules\Authorization\Application\UseCases\UpdateRole\UpdateRoleCommand($actor, $roleId, $input, $correlationId))->data;
    }

    public function createRole(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        return $this->createRole->handle(new \Modules\Authorization\Application\UseCases\CreateRole\CreateRoleCommand($actor, $input, $correlationId))->data;
    }

    public function cloneRole(AuthenticatedPrincipal $actor, string $sourceRoleId, array $input, string $correlationId): array
    {
        return $this->cloneRole->handle(new \Modules\Authorization\Application\UseCases\CloneRole\CloneRoleCommand($actor, $sourceRoleId, $input, $correlationId))->data;
    }

    public function replaceRolePermissions(AuthenticatedPrincipal $actor, string $roleId, array $permissionCodes, string $correlationId): array
    {
        return $this->replaceRolePermissions->handle(new \Modules\Authorization\Application\UseCases\ReplaceRolePermissions\ReplaceRolePermissionsCommand($actor, $roleId, $permissionCodes, $correlationId))->data;
    }

    public function listPermissions(AuthenticatedPrincipal $actor, ?string $moduleCode): array
    {
        return $this->listPermissions->handle(new \Modules\Authorization\Application\UseCases\ListPermissions\ListPermissionsCommand($actor, $moduleCode))->data;
    }

    public function createAssignments(AuthenticatedPrincipal $actor, string $userId, array $assignments, string $correlationId): array
    {
        return $this->createAssignments->handle(new \Modules\Authorization\Application\UseCases\CreateAssignments\CreateAssignmentsCommand($actor, $userId, $assignments, $correlationId))->data;
    }

    public function updateAssignment(
        AuthenticatedPrincipal $actor,
        string $userId,
        string $assignmentId,
        array $input,
        string $correlationId,
    ): array
    {
        return $this->updateAssignment->handle(new \Modules\Authorization\Application\UseCases\UpdateAssignment\UpdateAssignmentCommand($actor, $userId, $assignmentId, $input, $correlationId))->data;
    }

    public function revokeAssignment(AuthenticatedPrincipal $actor, string $userId, string $assignmentId, string $correlationId): void
    {
        $this->revokeAssignment->handle(new \Modules\Authorization\Application\UseCases\RevokeAssignment\RevokeAssignmentCommand($actor, $userId, $assignmentId, $correlationId));
    }

    public function listEntitlements(AuthenticatedPrincipal $actor, string $correlationId): array
    {
        return $this->listEntitlements->handle(new \Modules\Authorization\Application\UseCases\ListEntitlements\ListEntitlementsCommand($actor, $correlationId))->data;
    }

    public function assignInitial(string $hqId, string $userId, string $actorId, array $assignments, string $correlationId): void
    {
        $this->assignInitialRoles->handle(new \Modules\Authorization\Application\UseCases\AssignInitialRoles\AssignInitialRolesCommand($hqId, $userId, $actorId, $assignments, $correlationId));
    }

    public function invalidateUser(string $userId): void
    {
        $this->authorizationCacheInvalidator->invalidateUser($userId);
    }

    public function invalidateTenant(string $hqId): void
    {
        $this->authorizationCacheInvalidator->invalidateTenant($hqId);
    }

    public function assignmentOptions(AuthenticatedPrincipal $actor, ?string $roleId = null): array
    {
        return $this->getAssignmentOptions->handle(new \Modules\Authorization\Application\UseCases\GetAssignmentOptions\GetAssignmentOptionsCommand($actor, $roleId))->data;
    }
}
