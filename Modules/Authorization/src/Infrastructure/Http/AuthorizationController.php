<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Modules\Authorization\Application\RoleNavigation;
use Modules\Authorization\Application\AuthorizationService;
use Modules\Foundation\Application\ApiResponder;
use Modules\Foundation\Application\StrictPayload;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class AuthorizationController
{
    public function __construct(private AuthorizationService $authorization) {}

    public function context(Request $request): JsonResponse
    {
        return ApiResponder::success($request, $this->authorization->resolve($this->principal($request)));
    }

    public function nodes(Request $request): JsonResponse
    {
        return ApiResponder::success($request, $this->authorization->accessibleNodes($this->principal($request)));
    }

    public function assignmentOptions(Request $request): JsonResponse
    {
        $input = $request->validate(['role_id' => ['sometimes', 'nullable', 'uuid']]);
        return ApiResponder::success($request, $this->authorization->assignmentOptions($this->principal($request), $input['role_id'] ?? null));
    }

    public function roles(Request $request): JsonResponse
    {
        return ApiResponder::success($request, $this->authorization->listRoles($this->principal($request)));
    }

    public function role(Request $request, string $roleId): JsonResponse
    {
        return ApiResponder::success($request, $this->authorization->getRole($this->principal($request), $roleId));
    }

    public function updateRole(Request $request, string $roleId): JsonResponse
    {
        StrictPayload::assertOnly($request, ['role_title', 'description', 'status', 'permission_codes', 'menu_keys']);
        $input = $request->validate([
            'role_title' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'in:ACTIVE,INACTIVE'],
            'permission_codes' => ['sometimes', 'array'],
            'permission_codes.*' => ['required', 'string', 'distinct'],
            'menu_keys' => ['sometimes', 'nullable', 'array', 'max:27'],
            'menu_keys.*' => ['required', 'string', 'distinct', Rule::in(RoleNavigation::keys())],
        ]);
        if ($input === []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'role' => ['At least one role field is required.'],
            ]);
        }

        return ApiResponder::success($request, $this->authorization->updateRole(
            $this->principal($request),
            $roleId,
            $input,
            $this->correlationId($request),
        ));
    }

    public function createRole(Request $request): JsonResponse
    {
        StrictPayload::assertOnly($request, ['role_code', 'role_title', 'description', 'permission_codes', 'menu_keys']);
        $input = $request->validate([
            'role_code' => ['required', 'string', 'max:120', 'regex:/^[a-z][a-z0-9_.-]+$/'],
            'role_title' => ['required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'permission_codes' => ['present', 'array'],
            'permission_codes.*' => ['required', 'string', 'distinct'],
            'menu_keys' => ['sometimes', 'nullable', 'array', 'max:27'],
            'menu_keys.*' => ['required', 'string', 'distinct', Rule::in(RoleNavigation::keys())],
        ]);

        return ApiResponder::success($request, $this->authorization->createRole(
            $this->principal($request),
            $input,
            $this->correlationId($request),
        ), status: 201);
    }

    public function cloneRole(Request $request, string $roleId): JsonResponse
    {
        StrictPayload::assertOnly($request, ['role_code', 'role_title', 'description', 'permission_codes', 'menu_keys']);
        $input = $request->validate([
            'role_code' => ['required', 'string', 'max:120', 'regex:/^[a-z][a-z0-9_.-]+$/'],
            'role_title' => ['required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'permission_codes' => ['sometimes', 'array'],
            'permission_codes.*' => ['required', 'string', 'distinct'],
            'menu_keys' => ['sometimes', 'nullable', 'array', 'max:27'],
            'menu_keys.*' => ['required', 'string', 'distinct', Rule::in(RoleNavigation::keys())],
        ]);

        return ApiResponder::success($request, $this->authorization->cloneRole(
            $this->principal($request),
            $roleId,
            $input,
            $this->correlationId($request),
        ), status: 201);
    }

    public function replacePermissions(Request $request, string $roleId): JsonResponse
    {
        StrictPayload::assertOnly($request, ['permission_codes']);
        $input = $request->validate([
            'permission_codes' => ['present', 'array'],
            'permission_codes.*' => ['required', 'string', 'distinct'],
        ]);

        return ApiResponder::success($request, $this->authorization->replaceRolePermissions(
            $this->principal($request),
            $roleId,
            $input['permission_codes'],
            $this->correlationId($request),
        ));
    }

    public function permissions(Request $request): JsonResponse
    {
        $query = $request->validate([
            'module_code' => ['sometimes', 'nullable', 'string', 'max:80'],
        ]);

        return ApiResponder::success($request, $this->authorization->listPermissions(
            $this->principal($request),
            $query['module_code'] ?? null,
        ));
    }

    public function createAssignments(Request $request, string $userId): JsonResponse
    {
        StrictPayload::assertOnly($request, ['assignments']);
        $input = $request->validate([
            'assignments' => ['required', 'array', 'min:1'],
            'assignments.*.role_id' => ['required', 'uuid'],
            'assignments.*.scope_type' => ['required', 'in:PLATFORM,TENANT,AREA,NODE,VENDOR,VENDOR_BRANCH,SELF'],
            'assignments.*.scope_id' => ['sometimes', 'nullable', 'uuid'],
            'assignments.*.includes_descendants' => ['required', 'boolean'],
        ]);
        StrictPayload::assertItemsOnly(
            $input['assignments'],
            ['role_id', 'scope_type', 'scope_id', 'includes_descendants'],
            'assignments',
        );

        return ApiResponder::success($request, $this->authorization->createAssignments(
            $this->principal($request),
            $userId,
            $input['assignments'],
            $this->correlationId($request),
        ), status: 201);
    }

    public function updateAssignment(Request $request, string $userId, string $assignmentId): JsonResponse
    {
        StrictPayload::assertOnly($request, ['role_id', 'scope_type', 'scope_id', 'includes_descendants']);
        $input = $request->validate([
            'role_id' => ['required', 'uuid'], 'scope_type' => ['required', 'in:TENANT,AREA,NODE'],
            'scope_id' => ['sometimes', 'nullable', 'uuid'], 'includes_descendants' => ['required', 'boolean'],
        ]);
        return ApiResponder::success($request, $this->authorization->updateAssignment($this->principal($request), $userId, $assignmentId, $input, $this->correlationId($request)));
    }

    public function revokeAssignment(Request $request, string $userId, string $assignmentId): JsonResponse
    {
        StrictPayload::assertOnly($request, []);
        $this->authorization->revokeAssignment(
            $this->principal($request),
            $userId,
            $assignmentId,
            $this->correlationId($request),
        );

        return ApiResponder::success($request, ['success' => true]);
    }

    public function entitlements(Request $request): JsonResponse
    {
        return ApiResponder::success($request, $this->authorization->listEntitlements(
            $this->principal($request),
            $this->correlationId($request),
        ));
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
