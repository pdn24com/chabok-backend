<?php

declare(strict_types=1);

namespace Modules\User\Application;

use Modules\Foundation\Application\Data\Page;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\User\Application\Data\UserData;
use Modules\User\Application\UseCases\AttachOperationalProfile\AttachOperationalProfileCommand;
use Modules\User\Application\UseCases\AttachOperationalProfile\AttachOperationalProfileHandler;
use Modules\User\Application\UseCases\CreateUser\CreateUserCommand;
use Modules\User\Application\UseCases\CreateUser\CreateUserHandler;
use Modules\User\Application\UseCases\GetOwnProfile\GetOwnProfileCommand;
use Modules\User\Application\UseCases\GetOwnProfile\GetOwnProfileHandler;
use Modules\User\Application\UseCases\GetUser\GetUserCommand;
use Modules\User\Application\UseCases\GetUser\GetUserHandler;
use Modules\User\Application\UseCases\InviteUser\InviteUserCommand;
use Modules\User\Application\UseCases\InviteUser\InviteUserHandler;
use Modules\User\Application\UseCases\IssueTemporaryPassword\IssueTemporaryPasswordCommand;
use Modules\User\Application\UseCases\IssueTemporaryPassword\IssueTemporaryPasswordHandler;
use Modules\User\Application\UseCases\ListUsers\ListUsersCommand;
use Modules\User\Application\UseCases\ListUsers\ListUsersHandler;
use Modules\User\Application\UseCases\RevokeUserSessions\RevokeUserSessionsCommand;
use Modules\User\Application\UseCases\RevokeUserSessions\RevokeUserSessionsHandler;
use Modules\User\Application\UseCases\TransitionUser\TransitionUserCommand;
use Modules\User\Application\UseCases\TransitionUser\TransitionUserHandler;
use Modules\User\Application\UseCases\UpdateOwnProfile\UpdateOwnProfileCommand;
use Modules\User\Application\UseCases\UpdateOwnProfile\UpdateOwnProfileHandler;
use Modules\User\Application\UseCases\UpdateUser\UpdateUserCommand;
use Modules\User\Application\UseCases\UpdateUser\UpdateUserHandler;
/** Compatibility entry point for internal callers; each scenario is implemented by its handler. */

final readonly class UserService
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

    public function create(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        return $this->createHandler->handle(new CreateUserCommand($actor, $input, $correlationId))->data;
    }

    public function attachOperationalProfile(AuthenticatedPrincipal $actor, string $userId, array $input, string $correlationId): array
    {
        return $this->attachOperationalProfileHandler->handle(new AttachOperationalProfileCommand($actor, $userId, $input, $correlationId))->data;
    }

    public function list(
        AuthenticatedPrincipal $actor,
        int $page,
        int $pageSize,
        ?string $search,
        ?string $status,
        ?string $nodeId = null,
    ): Page
    {
        return $this->listHandler->handle(new ListUsersCommand($actor, $page, $pageSize, $search, $status, $nodeId))->data;
    }

    public function get(AuthenticatedPrincipal $actor, string $userId): array
    {
        return $this->getHandler->handle(new GetUserCommand($actor, $userId))->data;
    }

    public function update(AuthenticatedPrincipal $actor, string $userId, array $input, string $correlationId): array
    {
        return $this->updateHandler->handle(new UpdateUserCommand($actor, $userId, $input, $correlationId))->data;
    }

    public function updateSelf(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        return $this->updateSelfHandler->handle(new UpdateOwnProfileCommand($actor, $input, $correlationId))->data;
    }

    public function getSelf(AuthenticatedPrincipal $actor): array
    {
        return $this->getSelfHandler->handle(new GetOwnProfileCommand($actor))->data;
    }

    public function transition(AuthenticatedPrincipal $actor, string $userId, string $to, string $correlationId): array
    {
        return $this->transitionHandler->handle(new TransitionUserCommand($actor, $userId, $to, $correlationId))->data;
    }

    public function invite(AuthenticatedPrincipal $actor, string $userId, string $channel, string $correlationId): void
    {
        $this->inviteHandler->handle(new InviteUserCommand($actor, $userId, $channel, $correlationId));
    }

    public function temporaryPassword(AuthenticatedPrincipal $actor, string $userId, string $password, string $correlationId): void
    {
        $this->temporaryPasswordHandler->handle(new IssueTemporaryPasswordCommand($actor, $userId, $password, $correlationId));
    }

    public function revokeSessions(AuthenticatedPrincipal $actor, string $userId, string $correlationId): int
    {
        return $this->revokeSessionsHandler->handle(new RevokeUserSessionsCommand($actor, $userId, $correlationId))->data;
    }

    public function publicUser(array $user): array
    {
        return UserData::publicData($user);
    }
}
