<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        StrictPayload::assertOnly($request, ['role_title', 'description', 'status']);
        $input = $request->validate([
            'role_title' => ['sometimes', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'status' => ['sometimes', 'in:ACTIVE,INACTIVE'],
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

    public function cloneRole(Request $request, string $roleId): JsonResponse
    {
        StrictPayload::assertOnly($request, ['role_code', 'role_title', 'description']);
        $input = $request->validate([
            'role_code' => ['required', 'string', 'max:120', 'regex:/^[a-z][a-z0-9_.-]+$/'],
            'role_title' => ['required', 'string', 'max:200'],
            'description' => ['sometimes', 'nullable', 'string', 'max:500'],
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
            'permission_codes' => ['required', 'array'],
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
        return ApiResponder::success($request, $this->authorization->listEntitlements($this->principal($request)));
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
