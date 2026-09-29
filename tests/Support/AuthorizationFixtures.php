<?php

declare(strict_types=1);

namespace Tests\Support;

use Modules\Authorization\Application\Contracts\AuthorizationCacheInvalidatorInterface;
use Modules\Authorization\Application\Contracts\AuthorizationGuardInterface;
use Modules\Authorization\Application\Mappers\RoleInput;
use Modules\Authorization\Application\UseCases\AssignInitialRoles\AssignInitialRolesCommand;
use Modules\Authorization\Application\UseCases\AssignInitialRoles\AssignInitialRolesHandler;
use Modules\Authorization\Application\UseCases\CloneRole\CloneRoleCommand;
use Modules\Authorization\Application\UseCases\CloneRole\CloneRoleHandler;
use Modules\Authorization\Application\UseCases\CreateAssignments\CreateAssignmentsCommand;
use Modules\Authorization\Application\UseCases\CreateAssignments\CreateAssignmentsHandler;
use Modules\Authorization\Application\UseCases\CreateRole\CreateRoleCommand;
use Modules\Authorization\Application\UseCases\CreateRole\CreateRoleHandler;
use Modules\Authorization\Application\UseCases\GetAssignmentOptions\GetAssignmentOptionsCommand;
use Modules\Authorization\Application\UseCases\GetAssignmentOptions\GetAssignmentOptionsHandler;
use Modules\Authorization\Application\UseCases\GetRole\GetRoleCommand;
use Modules\Authorization\Application\UseCases\GetRole\GetRoleHandler;
use Modules\Authorization\Application\UseCases\ListAccessibleNodes\ListAccessibleNodesCommand;
use Modules\Authorization\Application\UseCases\ListAccessibleNodes\ListAccessibleNodesHandler;
use Modules\Authorization\Application\UseCases\ListEntitlements\ListEntitlementsCommand;
use Modules\Authorization\Application\UseCases\ListEntitlements\ListEntitlementsHandler;
use Modules\Authorization\Application\UseCases\ListPermissions\ListPermissionsCommand;
use Modules\Authorization\Application\UseCases\ListPermissions\ListPermissionsHandler;
use Modules\Authorization\Application\UseCases\ListRoles\ListRolesCommand;
use Modules\Authorization\Application\UseCases\ListRoles\ListRolesHandler;
use Modules\Authorization\Application\UseCases\ReplaceRolePermissions\ReplaceRolePermissionsCommand;
use Modules\Authorization\Application\UseCases\ReplaceRolePermissions\ReplaceRolePermissionsHandler;
use Modules\Authorization\Application\UseCases\ResolveContext\ResolveContextCommand;
use Modules\Authorization\Application\UseCases\ResolveContext\ResolveContextHandler;
use Modules\Authorization\Application\UseCases\RevokeAssignment\RevokeAssignmentCommand;
use Modules\Authorization\Application\UseCases\RevokeAssignment\RevokeAssignmentHandler;
use Modules\Authorization\Application\UseCases\UpdateAssignment\UpdateAssignmentCommand;
use Modules\Authorization\Application\UseCases\UpdateAssignment\UpdateAssignmentHandler;
use Modules\Authorization\Application\UseCases\UpdateRole\UpdateRoleCommand;
use Modules\Authorization\Application\UseCases\UpdateRole\UpdateRoleHandler;
use Modules\Authorization\Presentation\Http\Resources\AssignmentOptionsResource;
use Modules\Authorization\Presentation\Http\Resources\AssignmentResource;
use Modules\Authorization\Presentation\Http\Resources\RoleResource;
use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Application\Mappers\RoleAssignmentInput;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class AuthorizationFixtures
{
    public function __construct(
        private ResolveContextHandler $resolveContext,
        private AuthorizationGuardInterface $authorizationGuard,
        private ListAccessibleNodesHandler $listAccessibleNodes,
        private ListRolesHandler $listRoles,
        private GetRoleHandler $getRole,
        private UpdateRoleHandler $updateRole,
        private CreateRoleHandler $createRole,
        private CloneRoleHandler $cloneRole,
        private ReplaceRolePermissionsHandler $replaceRolePermissions,
        private ListPermissionsHandler $listPermissions,
        private CreateAssignmentsHandler $createAssignments,
        private UpdateAssignmentHandler $updateAssignment,
        private RevokeAssignmentHandler $revokeAssignment,
        private ListEntitlementsHandler $listEntitlements,
        private AssignInitialRolesHandler $assignInitialRoles,
        private AuthorizationCacheInvalidatorInterface $authorizationCacheInvalidator,
        private GetAssignmentOptionsHandler $getAssignmentOptions,
    ) {}

    public function resolve(AuthenticatedPrincipal $principal): AccessContextDto
    {
        return $this->resolveContext->handle(new ResolveContextCommand($principal));
    }

    public function assertPermission(
        AuthenticatedPrincipal $actor,
        string $permissionCode,
        ?string $expectedHqId = null,
    ): void {
        $this->authorizationGuard->assertPermission($actor, $permissionCode, $expectedHqId);
    }

    public function accessibleNodes(AuthenticatedPrincipal $actor): array
    {
        return $this->listAccessibleNodes->handle(new ListAccessibleNodesCommand($actor));
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
        return RoleResource::collection($this->listRoles->handle(new ListRolesCommand($actor)))->resolve();
    }

    public function getRole(AuthenticatedPrincipal $actor, string $roleId): array
    {
        return (new RoleResource($this->getRole->handle(new GetRoleCommand($actor, $roleId))))->resolve();
    }

    public function updateRole(
        AuthenticatedPrincipal $actor,
        string $roleId,
        array $input,
        string $correlationId,
    ): array {
        return (new RoleResource($this->updateRole->handle(new UpdateRoleCommand($actor, $roleId, RoleInput::changes($input), $correlationId))))->resolve();
    }

    public function createRole(
        AuthenticatedPrincipal $actor,
        array $input,
        string $correlationId,
    ): array {
        return (new RoleResource($this->createRole->handle(new CreateRoleCommand($actor, RoleInput::draft($input), $correlationId))))->resolve();
    }

    public function cloneRole(
        AuthenticatedPrincipal $actor,
        string $sourceRoleId,
        array $input,
        string $correlationId,
    ): array {
        return (new RoleResource($this->cloneRole->handle(new CloneRoleCommand($actor, $sourceRoleId, RoleInput::draft($input), $correlationId))))->resolve();
    }

    public function replaceRolePermissions(
        AuthenticatedPrincipal $actor,
        string $roleId,
        array $permissionCodes,
        string $correlationId,
    ): array {
        return (new RoleResource($this->replaceRolePermissions->handle(new ReplaceRolePermissionsCommand($actor, $roleId, $permissionCodes, $correlationId))))->resolve();
    }

    public function listPermissions(AuthenticatedPrincipal $actor, ?string $moduleCode): array
    {
        return $this->listPermissions->handle(new ListPermissionsCommand($actor, $moduleCode));
    }

    public function createAssignments(
        AuthenticatedPrincipal $actor,
        string $userId,
        array $assignments,
        string $correlationId,
    ): array {
        return AssignmentResource::collection($this->createAssignments->handle(new CreateAssignmentsCommand($actor, $userId, RoleAssignmentInput::drafts($assignments), $correlationId)))->resolve();
    }

    public function updateAssignment(
        AuthenticatedPrincipal $actor,
        string $userId,
        string $assignmentId,
        array $input,
        string $correlationId,
    ): array {
        return (new AssignmentResource($this->updateAssignment->handle(new UpdateAssignmentCommand($actor, $userId, $assignmentId, RoleAssignmentInput::draft($input), $correlationId))))->resolve();
    }

    public function revokeAssignment(
        AuthenticatedPrincipal $actor,
        string $userId,
        string $assignmentId,
        string $correlationId,
    ): void {
        $this->revokeAssignment->handle(new RevokeAssignmentCommand($actor, $userId, $assignmentId, $correlationId));
    }

    public function listEntitlements(AuthenticatedPrincipal $actor, string $correlationId): array
    {
        return $this->listEntitlements->handle(new ListEntitlementsCommand($actor, $correlationId));
    }

    public function assignInitial(
        string $hqId,
        string $userId,
        string $actorId,
        array $assignments,
        string $correlationId,
    ): void {
        $this->assignInitialRoles->handle(new AssignInitialRolesCommand($hqId, $userId, $actorId, RoleAssignmentInput::drafts($assignments), $correlationId));
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
        return (new AssignmentOptionsResource($this->getAssignmentOptions->handle(new GetAssignmentOptionsCommand($actor, $roleId))))->resolve();
    }
}
