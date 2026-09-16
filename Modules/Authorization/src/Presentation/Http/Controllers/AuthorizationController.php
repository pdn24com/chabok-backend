<?php

declare(strict_types=1);

namespace Modules\Authorization\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Foundation\Presentation\Http\StrictPayload;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class AuthorizationController
{
    public function __construct(
        private \Modules\Authorization\Application\UseCases\ResolveContext\ResolveContextHandler $resolve,
        private \Modules\Authorization\Application\UseCases\ListAccessibleNodes\ListAccessibleNodesHandler $accessibleNodes,
        private \Modules\Authorization\Application\UseCases\GetAssignmentOptions\GetAssignmentOptionsHandler $assignmentOptions,
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
    )
    {
    }

    public function context(Request $request): JsonResponse
    {
        return ApiResponder::success($request, $this->resolve->handle(new \Modules\Authorization\Application\UseCases\ResolveContext\ResolveContextCommand($this->principal($request)))->data);
    }

    public function nodes(Request $request): JsonResponse
    {
        return ApiResponder::success($request, $this->accessibleNodes->handle(new \Modules\Authorization\Application\UseCases\ListAccessibleNodes\ListAccessibleNodesCommand($this->principal($request)))->data);
    }

    public function assignmentOptions(\Modules\Authorization\Presentation\Http\Requests\AssignmentOptionsRequest $request): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->assignmentOptions->handle(new \Modules\Authorization\Application\UseCases\GetAssignmentOptions\GetAssignmentOptionsCommand($this->principal($request), $input['role_id'] ?? null))->data);
    }

    public function roles(Request $request): JsonResponse
    {
        return ApiResponder::success($request, $this->listRoles->handle(new \Modules\Authorization\Application\UseCases\ListRoles\ListRolesCommand($this->principal($request)))->data);
    }

    public function role(Request $request, string $roleId): JsonResponse
    {
        return ApiResponder::success($request, $this->getRole->handle(new \Modules\Authorization\Application\UseCases\GetRole\GetRoleCommand($this->principal($request), $roleId))->data);
    }

    public function updateRole(\Modules\Authorization\Presentation\Http\Requests\UpdateRoleRequest $request, string $roleId): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->updateRole->handle(new \Modules\Authorization\Application\UseCases\UpdateRole\UpdateRoleCommand($this->principal($request), $roleId, $input, $this->correlationId($request)))->data);
    }

    public function createRole(\Modules\Authorization\Presentation\Http\Requests\CreateRoleRequest $request): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->createRole->handle(new \Modules\Authorization\Application\UseCases\CreateRole\CreateRoleCommand($this->principal($request), $input, $this->correlationId($request)))->data, status: 201);
    }

    public function cloneRole(\Modules\Authorization\Presentation\Http\Requests\CloneRoleRequest $request, string $roleId): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->cloneRole->handle(new \Modules\Authorization\Application\UseCases\CloneRole\CloneRoleCommand($this->principal($request), $roleId, $input, $this->correlationId($request)))->data, status: 201);
    }

    public function replacePermissions(\Modules\Authorization\Presentation\Http\Requests\ReplacePermissionsRequest $request, string $roleId): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->replaceRolePermissions->handle(new \Modules\Authorization\Application\UseCases\ReplaceRolePermissions\ReplaceRolePermissionsCommand($this->principal($request), $roleId, $input['permission_codes'], $this->correlationId($request)))->data);
    }

    public function permissions(\Modules\Authorization\Presentation\Http\Requests\PermissionsRequest $request): JsonResponse
    {
        $query = $request->validated();
        return ApiResponder::success($request, $this->listPermissions->handle(new \Modules\Authorization\Application\UseCases\ListPermissions\ListPermissionsCommand($this->principal($request), $query['module_code'] ?? null))->data);
    }

    public function createAssignments(\Modules\Authorization\Presentation\Http\Requests\CreateAssignmentsRequest $request, string $userId): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->createAssignments->handle(new \Modules\Authorization\Application\UseCases\CreateAssignments\CreateAssignmentsCommand($this->principal($request), $userId, $input['assignments'], $this->correlationId($request)))->data, status: 201);
    }

    public function updateAssignment(
        \Modules\Authorization\Presentation\Http\Requests\UpdateAssignmentRequest $request,
        string $userId,
        string $assignmentId,
    ): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->updateAssignment->handle(new \Modules\Authorization\Application\UseCases\UpdateAssignment\UpdateAssignmentCommand($this->principal($request), $userId, $assignmentId, $input, $this->correlationId($request)))->data);
    }

    public function revokeAssignment(Request $request, string $userId, string $assignmentId): JsonResponse
    {
        StrictPayload::assertOnly($request, []);
        $this->revokeAssignment->handle(new \Modules\Authorization\Application\UseCases\RevokeAssignment\RevokeAssignmentCommand($this->principal($request), $userId, $assignmentId, $this->correlationId($request)));
        return ApiResponder::success($request, ['success' => true]);
    }

    public function entitlements(Request $request): JsonResponse
    {
        return ApiResponder::success($request, $this->listEntitlements->handle(new \Modules\Authorization\Application\UseCases\ListEntitlements\ListEntitlementsCommand($this->principal($request), $this->correlationId($request)))->data);
    }

    private function principal(Request $request): AuthenticatedPrincipal
    {
        return $request->attributes->get('principal');
    }

    private function correlationId(Request $request): string
    {
        return (string) $request->attributes->get('correlation_id');
    }
}
