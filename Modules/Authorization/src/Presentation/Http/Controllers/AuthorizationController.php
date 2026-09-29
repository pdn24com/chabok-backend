<?php

declare(strict_types=1);

namespace Modules\Authorization\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Authorization\Application\Mappers\RoleInput;
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
use Modules\Authorization\Presentation\Http\Requests\AssignmentOptionsRequest;
use Modules\Authorization\Presentation\Http\Requests\CloneRoleRequest;
use Modules\Authorization\Presentation\Http\Requests\CreateAssignmentsRequest;
use Modules\Authorization\Presentation\Http\Requests\CreateRoleRequest;
use Modules\Authorization\Presentation\Http\Requests\PermissionsRequest;
use Modules\Authorization\Presentation\Http\Requests\ReplacePermissionsRequest;
use Modules\Authorization\Presentation\Http\Requests\UpdateAssignmentRequest;
use Modules\Authorization\Presentation\Http\Requests\UpdateRoleRequest;
use Modules\Authorization\Presentation\Http\Resources\AssignmentOptionsResource;
use Modules\Authorization\Presentation\Http\Resources\AssignmentResource;
use Modules\Authorization\Presentation\Http\Resources\PermissionResource;
use Modules\Authorization\Presentation\Http\Resources\RoleResource;
use Modules\Foundation\Application\Mappers\RoleAssignmentInput;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Foundation\Presentation\Http\Resources\AccessContextResource;
use Modules\Foundation\Presentation\Http\StrictPayload;

final class AuthorizationController
{
    public function context(Request $request, ResolveContextHandler $resolveContextHandler): JsonResponse
    {
        return ApiResponder::success($request, (new AccessContextResource($resolveContextHandler->handle(new ResolveContextCommand($request->attributes->get('principal')))))->resolve($request));
    }

    public function nodes(Request $request, ListAccessibleNodesHandler $listAccessibleNodesHandler): JsonResponse
    {
        return ApiResponder::success($request, $listAccessibleNodesHandler->handle(new ListAccessibleNodesCommand($request->attributes->get('principal'))));
    }

    public function assignmentOptions(AssignmentOptionsRequest $request, GetAssignmentOptionsHandler $getAssignmentOptionsHandler): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new AssignmentOptionsResource($getAssignmentOptionsHandler->handle(new GetAssignmentOptionsCommand($request->attributes->get('principal'), $input['role_id'] ?? null))))->resolve($request));
    }

    public function roles(Request $request, ListRolesHandler $listRolesHandler): JsonResponse
    {
        return ApiResponder::success($request, RoleResource::collection($listRolesHandler->handle(new ListRolesCommand($request->attributes->get('principal'))))->resolve($request));
    }

    public function role(Request $request, GetRoleHandler $getRoleHandler, string $roleId): JsonResponse
    {
        return ApiResponder::success($request, (new RoleResource($getRoleHandler->handle(new GetRoleCommand($request->attributes->get('principal'), $roleId))))->resolve($request));
    }

    public function updateRole(UpdateRoleRequest $request, UpdateRoleHandler $updateRoleHandler, string $roleId): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new RoleResource($updateRoleHandler->handle(new UpdateRoleCommand($request->attributes->get('principal'), $roleId, RoleInput::changes($input), (string) $request->attributes->get('correlation_id')))))->resolve($request));
    }

    public function createRole(CreateRoleRequest $request, CreateRoleHandler $createRoleHandler): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new RoleResource($createRoleHandler->handle(new CreateRoleCommand($request->attributes->get('principal'), RoleInput::draft($input), (string) $request->attributes->get('correlation_id')))))->resolve($request), status: 201);
    }

    public function cloneRole(CloneRoleRequest $request, CloneRoleHandler $cloneRoleHandler, string $roleId): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new RoleResource($cloneRoleHandler->handle(new CloneRoleCommand($request->attributes->get('principal'), $roleId, RoleInput::draft($input), (string) $request->attributes->get('correlation_id')))))->resolve($request), status: 201);
    }

    public function replacePermissions(ReplacePermissionsRequest $request, ReplaceRolePermissionsHandler $replaceRolePermissionsHandler, string $roleId): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new RoleResource($replaceRolePermissionsHandler->handle(new ReplaceRolePermissionsCommand($request->attributes->get('principal'), $roleId, $input['permission_codes'], (string) $request->attributes->get('correlation_id')))))->resolve($request));
    }

    public function permissions(PermissionsRequest $request, ListPermissionsHandler $listPermissionsHandler): JsonResponse
    {
        $query = $request->validated();

        return ApiResponder::success($request, PermissionResource::collection($listPermissionsHandler->handle(new ListPermissionsCommand($request->attributes->get('principal'), $query['module_code'] ?? null))));
    }

    public function createAssignments(CreateAssignmentsRequest $request, CreateAssignmentsHandler $createAssignmentsHandler, string $userId): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, AssignmentResource::collection($createAssignmentsHandler->handle(new CreateAssignmentsCommand($request->attributes->get('principal'), $userId, RoleAssignmentInput::drafts($input['assignments']), (string) $request->attributes->get('correlation_id'))))->resolve($request), status: 201);
    }

    public function updateAssignment(
        UpdateAssignmentRequest $request, UpdateAssignmentHandler $updateAssignmentHandler,
        string $userId,
        string $assignmentId,
    ): JsonResponse {
        $input = $request->validated();

        return ApiResponder::success($request, (new AssignmentResource($updateAssignmentHandler->handle(new UpdateAssignmentCommand($request->attributes->get('principal'), $userId, $assignmentId, RoleAssignmentInput::draft($input), (string) $request->attributes->get('correlation_id')))))->resolve($request));
    }

    public function revokeAssignment(
        Request $request, RevokeAssignmentHandler $revokeAssignmentHandler,
        string $userId,
        string $assignmentId,
    ): JsonResponse {
        StrictPayload::assertOnly($request, []);
        $revokeAssignmentHandler->handle(new RevokeAssignmentCommand($request->attributes->get('principal'), $userId, $assignmentId, (string) $request->attributes->get('correlation_id')));

        return ApiResponder::success($request, ['success' => true]);
    }

    public function entitlements(Request $request, ListEntitlementsHandler $listEntitlementsHandler): JsonResponse
    {
        return ApiResponder::success($request, $listEntitlementsHandler->handle(new ListEntitlementsCommand($request->attributes->get('principal'), (string) $request->attributes->get('correlation_id'))));
    }
}
