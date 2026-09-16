<?php

declare(strict_types=1);

namespace Modules\User\Presentation\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Modules\User\Presentation\Http\Requests\TemporaryPasswordRequest;
use Modules\User\Presentation\Http\Requests\InviteUserRequest;
use Modules\User\Presentation\Http\Requests\OperationalProfileRequest;
use Modules\User\Presentation\Http\Requests\ListUsersRequest;
use Modules\User\Presentation\Http\Requests\UpdateProfileRequest;
use Modules\User\Presentation\Http\Requests\CreateUserRequest;
use Modules\Foundation\Presentation\Http\ApiResponder;
use Modules\Foundation\Presentation\Http\StrictPayload;
use Modules\User\Application\Data\UserData;
use Modules\User\Presentation\Mappers\UserCommandMapper;
use Modules\User\Application\UseCases\CreateUser\CreateUserHandler;
use Modules\User\Application\UseCases\AttachOperationalProfile\AttachOperationalProfileHandler;
use Modules\User\Application\UseCases\ListUsers\ListUsersHandler;
use Modules\User\Application\UseCases\GetUser\GetUserHandler;
use Modules\User\Application\UseCases\UpdateUser\UpdateUserHandler;
use Modules\User\Application\UseCases\UpdateOwnProfile\UpdateOwnProfileHandler;
use Modules\User\Application\UseCases\GetOwnProfile\GetOwnProfileHandler;
use Modules\User\Application\UseCases\TransitionUser\TransitionUserHandler;
use Modules\User\Application\UseCases\InviteUser\InviteUserHandler;
use Modules\User\Application\UseCases\IssueTemporaryPassword\IssueTemporaryPasswordHandler;
use Modules\User\Application\UseCases\RevokeUserSessions\RevokeUserSessionsHandler;

final readonly class UserController
{
    public function __construct(
        private CreateUserHandler $createHandler,
        private AttachOperationalProfileHandler $attachOperationalProfileHandler,
        private ListUsersHandler $listHandler,
        private GetUserHandler $getHandler,
        private UpdateUserHandler $updateHandler,
        private UpdateOwnProfileHandler $updateSelfHandler,
        private GetOwnProfileHandler $getSelfHandler,
        private TransitionUserHandler $transitionHandler,
        private InviteUserHandler $inviteHandler,
        private IssueTemporaryPasswordHandler $temporaryPasswordHandler,
        private RevokeUserSessionsHandler $revokeSessionsHandler,
    )
    {
    }

    public function index(ListUsersRequest $request): JsonResponse
    {
        $query = $request->validated();
        $paginator = $this->listHandler->handle(UserCommandMapper::list($request, (int) ($query['page'] ?? 1), (int) ($query['page_size'] ?? 25), $query['search'] ?? null, $query['status'] ?? null, $query['node_id'] ?? null))->data;
        return ApiResponder::paginated($request, $paginator, fn($row) => UserData::publicData((array) $row));
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponder::success($request, $this->getSelfHandler->handle(UserCommandMapper::getSelf($request))->data);
    }

    public function updateSelf(UpdateProfileRequest $request): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->updateSelfHandler->handle(UserCommandMapper::updateSelf($request, $input))->data);
    }

    public function store(CreateUserRequest $request): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->createHandler->handle(UserCommandMapper::create($request, $input))->data, status: 201);
    }

    public function operationalProfile(OperationalProfileRequest $request, string $userId): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->attachOperationalProfileHandler->handle(UserCommandMapper::attachOperationalProfile($request, $userId, $input['operational_profile']))->data);
    }

    public function show(Request $request, string $userId): JsonResponse
    {
        return ApiResponder::success($request, $this->getHandler->handle(UserCommandMapper::get($request, $userId))->data);
    }

    public function update(UpdateProfileRequest $request, string $userId): JsonResponse
    {
        $input = $request->validated();
        return ApiResponder::success($request, $this->updateHandler->handle(UserCommandMapper::update($request, $userId, $input))->data);
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

    public function invite(InviteUserRequest $request, string $userId): JsonResponse
    {
        $input = $request->validated();
        $this->inviteHandler->handle(UserCommandMapper::invite($request, $userId, $input['channel']));
        return ApiResponder::success($request, ['success' => true]);
    }

    public function temporaryPassword(TemporaryPasswordRequest $request, string $userId): JsonResponse
    {
        $input = $request->validated();
        $this->temporaryPasswordHandler->handle(UserCommandMapper::temporaryPassword($request, $userId, $input['temporary_password']));
        return ApiResponder::success($request, ['success' => true]);
    }

    public function revokeSessions(Request $request, string $userId): JsonResponse
    {
        return ApiResponder::success($request, ['revoked_session_count' => $this->revokeSessionsHandler->handle(UserCommandMapper::revokeSessions($request, $userId))->data]);
    }

    private function transition(Request $request, string $userId, string $status): JsonResponse
    {
        StrictPayload::assertOnly($request, []);
        return ApiResponder::success($request, $this->transitionHandler->handle(UserCommandMapper::transition($request, $userId, $status))->data);
    }
}
