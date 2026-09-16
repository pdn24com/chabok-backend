<?php

declare(strict_types=1);

namespace Modules\User\Infrastructure\Http;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Application\ApiResponder;
use Modules\Foundation\Application\StrictPayload;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\User\Application\UserService;

final readonly class UserController
{
    public function __construct(private UserService $users) {}

    public function index(Request $request): JsonResponse
    {
        $query = $request->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'page_size' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'search' => ['sometimes', 'nullable', 'string', 'max:254'],
            'status' => ['sometimes', 'nullable', 'in:INVITED,ACTIVE,SUSPENDED,DEACTIVATED'],
            'role_code' => ['sometimes', 'nullable', 'string'],
            'scope_id' => ['sometimes', 'nullable', 'uuid'],
            'node_id' => ['sometimes', 'nullable', 'uuid'],
        ]);
        $paginator = $this->users->list(
            $this->principal($request),
            (int) ($query['page'] ?? 1),
            (int) ($query['page_size'] ?? 25),
            $query['search'] ?? null,
            $query['status'] ?? null,
            $query['node_id'] ?? null,
        );

        return ApiResponder::paginated($request, $paginator, fn ($row) => $this->users->publicUser((array) $row));
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponder::success(
            $request,
            $this->users->getSelf($this->principal($request)),
        );
    }

    public function updateSelf(Request $request): JsonResponse
    {
        StrictPayload::assertOnly($request, ['first_name', 'last_name', 'display_name']);
        $input = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:120'],
            'last_name' => ['sometimes', 'string', 'max:120'],
            'display_name' => ['sometimes', 'string', 'max:240'],
        ]);
        if ($input === []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'profile' => ['At least one profile field is required.'],
            ]);
        }

        return ApiResponder::success($request, $this->users->updateSelf(
            $this->principal($request),
            $input,
            $this->correlationId($request),
        ));
    }

    public function store(Request $request): JsonResponse
    {
        StrictPayload::assertOnly($request, [
            'creation_mode', 'username', 'mobile', 'email', 'first_name',
            'last_name', 'temporary_password', 'assignments', 'operational_profile',
        ]);
        $input = $request->validate([
            'creation_mode' => ['required', 'in:DIRECT_ACTIVE,SMS_INVITATION,EMAIL_INVITATION'],
            'username' => ['sometimes', 'nullable', 'string', 'max:100'],
            'mobile' => ['sometimes', 'nullable', 'string', 'max:32'],
            'email' => ['sometimes', 'nullable', 'email', 'max:254'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'temporary_password' => ['sometimes', 'string'],
            'assignments' => ['present', 'array', 'max:50'],
            'assignments.*.role_id' => ['required', 'uuid'],
            'assignments.*.scope_type' => ['required', 'in:PLATFORM,TENANT,AREA,NODE,VENDOR,VENDOR_BRANCH,SELF'],
            'assignments.*.scope_id' => ['sometimes', 'nullable', 'uuid'],
            'assignments.*.includes_descendants' => ['required', 'boolean'],
        ] + OperationalProfileRules::rules((array) $request->input('operational_profile', [])));
        StrictPayload::assertItemsOnly(
            $input['assignments'],
            ['role_id', 'scope_type', 'scope_id', 'includes_descendants'],
            'assignments',
        );
        if (empty($input['username']) && empty($input['mobile']) && empty($input['email'])) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'identifier' => ['At least one identifier is required.'],
            ]);
        }

        return ApiResponder::success(
            $request,
            $this->users->create($this->principal($request), $input, $this->correlationId($request)),
            status: 201,
        );
    }

    public function operationalProfile(Request $request, string $userId): JsonResponse
    {
        StrictPayload::assertOnly($request, ['operational_profile']);
        $input = $request->validate(['operational_profile' => ['required', 'array:kind,mode,existing_id,expected_version,role_id,driver,node']] + OperationalProfileRules::rules((array) $request->input('operational_profile', [])));
        return ApiResponder::success($request, $this->users->attachOperationalProfile($this->principal($request), $userId, $input['operational_profile'], $this->correlationId($request)));
    }

    public function show(Request $request, string $userId): JsonResponse
    {
        return ApiResponder::success($request, $this->users->get($this->principal($request), $userId));
    }

    public function update(Request $request, string $userId): JsonResponse
    {
        StrictPayload::assertOnly($request, ['first_name', 'last_name', 'display_name']);
        $input = $request->validate([
            'first_name' => ['sometimes', 'string', 'max:120'],
            'last_name' => ['sometimes', 'string', 'max:120'],
            'display_name' => ['sometimes', 'string', 'max:240'],
        ]);
        if ($input === []) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'profile' => ['At least one profile field is required.'],
            ]);
        }

        return ApiResponder::success($request, $this->users->update(
            $this->principal($request),
            $userId,
            $input,
            $this->correlationId($request),
        ));
    }

    public function suspend(Request $request, string $userId): JsonResponse
    {
        return $this->transition($request, $userId, 'SUSPENDED');
    }

    public function activate(Request $request, string $userId): JsonResponse
    {
        return $this->transition($request, $userId, 'ACTIVE');
    }

    public function deactivate(Request $request, string $userId): JsonResponse
    {
        return $this->transition($request, $userId, 'DEACTIVATED');
    }

    public function invite(Request $request, string $userId): JsonResponse
    {
        StrictPayload::assertOnly($request, ['channel']);
        $input = $request->validate(['channel' => ['required', 'in:SMS,EMAIL']]);
        $this->users->invite($this->principal($request), $userId, $input['channel'], $this->correlationId($request));

        return ApiResponder::success($request, ['success' => true]);
    }

    public function temporaryPassword(Request $request, string $userId): JsonResponse
    {
        StrictPayload::assertOnly($request, ['temporary_password']);
        $input = $request->validate(['temporary_password' => ['required', 'string']]);
        $this->users->temporaryPassword($this->principal($request), $userId, $input['temporary_password'], $this->correlationId($request));

        return ApiResponder::success($request, ['success' => true]);
    }

    public function revokeSessions(Request $request, string $userId): JsonResponse
    {
        return ApiResponder::success($request, ['revoked_session_count' => $this->users->revokeSessions(
            $this->principal($request),
            $userId,
            $this->correlationId($request),
        )]);
    }

    private function transition(Request $request, string $userId, string $status): JsonResponse
    {
        StrictPayload::assertOnly($request, []);

        return ApiResponder::success($request, $this->users->transition(
            $this->principal($request),
            $userId,
            $status,
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
