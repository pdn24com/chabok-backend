<?php

declare(strict_types=1);

namespace Modules\Iam\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Foundation\Presentation\Http\StrictPayload;
use Modules\Iam\Application\UseCases\AttachOperationalProfile\AttachOperationalProfileHandler;
use Modules\Iam\Application\UseCases\CreateUser\CreateUserHandler;
use Modules\Iam\Application\UseCases\GetOwnProfile\GetOwnProfileHandler;
use Modules\Iam\Application\UseCases\GetUser\GetUserHandler;
use Modules\Iam\Application\UseCases\InviteUser\InviteUserHandler;
use Modules\Iam\Application\UseCases\IssueTemporaryPassword\IssueTemporaryPasswordHandler;
use Modules\Iam\Application\UseCases\ListUsers\ListUsersHandler;
use Modules\Iam\Application\UseCases\RevokeUserSessions\RevokeUserSessionsHandler;
use Modules\Iam\Application\UseCases\TransitionUser\TransitionUserHandler;
use Modules\Iam\Application\UseCases\UpdateOwnProfile\UpdateOwnProfileHandler;
use Modules\Iam\Application\UseCases\UpdateUser\UpdateUserHandler;
use Modules\Iam\Domain\Enums\UserStatus;
use Modules\Iam\Presentation\Http\Requests\CreateUserRequest;
use Modules\Iam\Presentation\Http\Requests\InviteUserRequest;
use Modules\Iam\Presentation\Http\Requests\ListUsersRequest;
use Modules\Iam\Presentation\Http\Requests\OperationalProfileRequest;
use Modules\Iam\Presentation\Http\Requests\TemporaryPasswordRequest;
use Modules\Iam\Presentation\Http\Requests\UpdateProfileRequest;
use Modules\Iam\Presentation\Http\Resources\DriverProfileResource;
use Modules\Iam\Presentation\Http\Resources\UserDetailResource;
use Modules\Iam\Presentation\Http\Resources\UserResource;
use Modules\Iam\Presentation\Http\Resources\UserStatusResource;
use Modules\Iam\Presentation\Mappers\UserCommandMapper;

final class UserController
{
    public function index(ListUsersRequest $request, ListUsersHandler $listUsersHandler): JsonResponse
    {
        $query = $request->validated();
        $paginator = $listUsersHandler->handle(UserCommandMapper::list($request, (int) ($query['page'] ?? 1), (int) ($query['page_size'] ?? 25), $query['search'] ?? null, $query['status'] ?? null, $query['node_id'] ?? null));

        return ApiResponder::paginated($request, $paginator, fn ($row) => (new UserResource($row))->resolve($request));
    }

    public function me(Request $request, GetOwnProfileHandler $getOwnProfileHandler): JsonResponse
    {
        return ApiResponder::success($request, (new UserResource($getOwnProfileHandler->handle(UserCommandMapper::getSelf($request))))->resolve($request));
    }

    public function updateSelf(UpdateProfileRequest $request, UpdateOwnProfileHandler $updateOwnProfileHandler): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new UserResource($updateOwnProfileHandler->handle(UserCommandMapper::updateSelf($request, $input))))->resolve($request));
    }

    public function store(CreateUserRequest $request, CreateUserHandler $createUserHandler): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new UserResource($createUserHandler->handle(UserCommandMapper::create($request, $input))))->resolve($request), status: 201);
    }

    public function operationalProfile(OperationalProfileRequest $request, AttachOperationalProfileHandler $attachOperationalProfileHandler, string $userId): JsonResponse
    {
        $input = $request->validated();

        $profile = $attachOperationalProfileHandler->handle(UserCommandMapper::attachOperationalProfile($request, $userId, $input['operational_profile']));

        return ApiResponder::success($request, ['driver_profile' => $profile === null ? null : (new DriverProfileResource($profile))->resolve($request)]);
    }

    public function show(Request $request, GetUserHandler $getUserHandler, string $userId): JsonResponse
    {
        return ApiResponder::success($request, (new UserDetailResource($getUserHandler->handle(UserCommandMapper::get($request, $userId))))->resolve($request));
    }

    public function update(UpdateProfileRequest $request, UpdateUserHandler $updateUserHandler, string $userId): JsonResponse
    {
        $input = $request->validated();

        return ApiResponder::success($request, (new UserResource($updateUserHandler->handle(UserCommandMapper::update($request, $userId, $input))))->resolve($request));
    }

    public function suspend(Request $request, TransitionUserHandler $transitionUserHandler, string $userId): JsonResponse
    {
        return $this->transition($request, $transitionUserHandler, $userId, UserStatus::Suspended);
    }

    public function activate(Request $request, TransitionUserHandler $transitionUserHandler, string $userId): JsonResponse
    {
        return $this->transition($request, $transitionUserHandler, $userId, UserStatus::Active);
    }

    public function deactivate(Request $request, TransitionUserHandler $transitionUserHandler, string $userId): JsonResponse
    {
        return $this->transition($request, $transitionUserHandler, $userId, UserStatus::Deactivated);
    }

    public function invite(InviteUserRequest $request, InviteUserHandler $inviteUserHandler, string $userId): JsonResponse
    {
        $input = $request->validated();
        $inviteUserHandler->handle(UserCommandMapper::invite($request, $userId, $input['channel']));

        return ApiResponder::success($request, ['success' => true]);
    }

    public function temporaryPassword(TemporaryPasswordRequest $request, IssueTemporaryPasswordHandler $issueTemporaryPasswordHandler, string $userId): JsonResponse
    {
        $input = $request->validated();
        $issueTemporaryPasswordHandler->handle(UserCommandMapper::temporaryPassword($request, $userId, $input['temporary_password']));

        return ApiResponder::success($request, ['success' => true]);
    }

    public function revokeSessions(Request $request, RevokeUserSessionsHandler $revokeUserSessionsHandler, string $userId): JsonResponse
    {
        return ApiResponder::success($request, [
            'revoked_session_count' => $revokeUserSessionsHandler->handle(UserCommandMapper::revokeSessions($request, $userId)),
        ]);
    }

    private function transition(
        Request $request, TransitionUserHandler $transitionUserHandler,
        string $userId,
        UserStatus $status,
    ): JsonResponse {
        StrictPayload::assertOnly($request, []);

        return ApiResponder::success($request, (new UserStatusResource($transitionUserHandler->handle(UserCommandMapper::transition($request, $userId, $status))))->resolve($request));
    }
}
