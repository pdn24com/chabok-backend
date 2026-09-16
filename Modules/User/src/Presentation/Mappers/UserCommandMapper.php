<?php

declare(strict_types=1);

namespace Modules\User\Presentation\Mappers;

use Illuminate\Http\Request;
use Modules\User\Application\UseCases\CreateUser\CreateUserCommand;
use Modules\User\Application\UseCases\AttachOperationalProfile\AttachOperationalProfileCommand;
use Modules\User\Application\UseCases\ListUsers\ListUsersCommand;
use Modules\User\Application\UseCases\GetUser\GetUserCommand;
use Modules\User\Application\UseCases\UpdateUser\UpdateUserCommand;
use Modules\User\Application\UseCases\UpdateOwnProfile\UpdateOwnProfileCommand;
use Modules\User\Application\UseCases\GetOwnProfile\GetOwnProfileCommand;
use Modules\User\Application\UseCases\TransitionUser\TransitionUserCommand;
use Modules\User\Application\UseCases\InviteUser\InviteUserCommand;
use Modules\User\Application\UseCases\IssueTemporaryPassword\IssueTemporaryPasswordCommand;
use Modules\User\Application\UseCases\RevokeUserSessions\RevokeUserSessionsCommand;

final class UserCommandMapper
{
    public static function create(Request $request, array $input): CreateUserCommand
    {
        return new CreateUserCommand($request->attributes->get('principal'), $input, (string) $request->attributes->get('correlation_id'));
    }

    public static function attachOperationalProfile(Request $request, string $userId, array $input): AttachOperationalProfileCommand
    {
        return new AttachOperationalProfileCommand($request->attributes->get('principal'), $userId, $input, (string) $request->attributes->get('correlation_id'));
    }

    public static function list(Request $request, int $page, int $pageSize, ?string $search, ?string $status, ?string $nodeId = null): ListUsersCommand
    {
        return new ListUsersCommand($request->attributes->get('principal'), $page, $pageSize, $search, $status, $nodeId);
    }

    public static function get(Request $request, string $userId): GetUserCommand
    {
        return new GetUserCommand($request->attributes->get('principal'), $userId);
    }

    public static function update(Request $request, string $userId, array $input): UpdateUserCommand
    {
        return new UpdateUserCommand($request->attributes->get('principal'), $userId, $input, (string) $request->attributes->get('correlation_id'));
    }

    public static function updateSelf(Request $request, array $input): UpdateOwnProfileCommand
    {
        return new UpdateOwnProfileCommand($request->attributes->get('principal'), $input, (string) $request->attributes->get('correlation_id'));
    }

    public static function getSelf(Request $request): GetOwnProfileCommand
    {
        return new GetOwnProfileCommand($request->attributes->get('principal'));
    }

    public static function transition(Request $request, string $userId, string $to): TransitionUserCommand
    {
        return new TransitionUserCommand($request->attributes->get('principal'), $userId, $to, (string) $request->attributes->get('correlation_id'));
    }

    public static function invite(Request $request, string $userId, string $channel): InviteUserCommand
    {
        return new InviteUserCommand($request->attributes->get('principal'), $userId, $channel, (string) $request->attributes->get('correlation_id'));
    }

    public static function temporaryPassword(Request $request, string $userId, string $password): IssueTemporaryPasswordCommand
    {
        return new IssueTemporaryPasswordCommand($request->attributes->get('principal'), $userId, $password, (string) $request->attributes->get('correlation_id'));
    }

    public static function revokeSessions(Request $request, string $userId): RevokeUserSessionsCommand
    {
        return new RevokeUserSessionsCommand($request->attributes->get('principal'), $userId, (string) $request->attributes->get('correlation_id'));
    }
}
