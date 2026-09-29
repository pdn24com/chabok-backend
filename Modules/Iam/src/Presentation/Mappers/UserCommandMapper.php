<?php

declare(strict_types=1);

namespace Modules\Iam\Presentation\Mappers;

use Illuminate\Http\Request;
use Modules\Iam\Application\Dto\ProfileChangesDto;
use Modules\Iam\Application\Mappers\UserCreationInput;
use Modules\Iam\Application\UseCases\AttachOperationalProfile\AttachOperationalProfileCommand;
use Modules\Iam\Application\UseCases\CreateUser\CreateUserCommand;
use Modules\Iam\Application\UseCases\GetOwnProfile\GetOwnProfileCommand;
use Modules\Iam\Application\UseCases\GetUser\GetUserCommand;
use Modules\Iam\Application\UseCases\InviteUser\InviteUserCommand;
use Modules\Iam\Application\UseCases\IssueTemporaryPassword\IssueTemporaryPasswordCommand;
use Modules\Iam\Application\UseCases\ListUsers\ListUsersCommand;
use Modules\Iam\Application\UseCases\RevokeUserSessions\RevokeUserSessionsCommand;
use Modules\Iam\Application\UseCases\TransitionUser\TransitionUserCommand;
use Modules\Iam\Application\UseCases\UpdateOwnProfile\UpdateOwnProfileCommand;
use Modules\Iam\Application\UseCases\UpdateUser\UpdateUserCommand;
use Modules\Iam\Domain\Enums\UserStatus;

final class UserCommandMapper
{
    public static function create(Request $request, array $input): CreateUserCommand
    {
        return new CreateUserCommand($request->attributes->get('principal'), UserCreationInput::user($input), (string) $request->attributes->get('correlation_id'));
    }

    public static function attachOperationalProfile(
        Request $request,
        string $userId,
        array $input,
    ): AttachOperationalProfileCommand {
        return new AttachOperationalProfileCommand($request->attributes->get('principal'), $userId, UserCreationInput::profile($input), (string) $request->attributes->get('correlation_id'));
    }

    public static function list(
        Request $request,
        int $page,
        int $pageSize,
        ?string $search,
        ?string $status,
        ?string $nodeId = null,
    ): ListUsersCommand {
        return new ListUsersCommand($request->attributes->get('principal'), $page, $pageSize, $search, $status, $nodeId);
    }

    public static function get(Request $request, string $userId): GetUserCommand
    {
        return new GetUserCommand($request->attributes->get('principal'), $userId);
    }

    public static function update(
        Request $request,
        string $userId,
        array $input,
    ): UpdateUserCommand {
        return new UpdateUserCommand($request->attributes->get('principal'), $userId, new ProfileChangesDto($input['first_name'] ?? null, $input['last_name'] ?? null, $input['display_name'] ?? null), (string) $request->attributes->get('correlation_id'));
    }

    public static function updateSelf(Request $request, array $input): UpdateOwnProfileCommand
    {
        return new UpdateOwnProfileCommand($request->attributes->get('principal'), new ProfileChangesDto($input['first_name'] ?? null, $input['last_name'] ?? null, $input['display_name'] ?? null), (string) $request->attributes->get('correlation_id'));
    }

    public static function getSelf(Request $request): GetOwnProfileCommand
    {
        return new GetOwnProfileCommand($request->attributes->get('principal'));
    }

    public static function transition(
        Request $request,
        string $userId,
        UserStatus $to,
    ): TransitionUserCommand {
        return new TransitionUserCommand($request->attributes->get('principal'), $userId, $to, (string) $request->attributes->get('correlation_id'));
    }

    public static function invite(
        Request $request,
        string $userId,
        string $channel,
    ): InviteUserCommand {
        return new InviteUserCommand($request->attributes->get('principal'), $userId, $channel, (string) $request->attributes->get('correlation_id'));
    }

    public static function temporaryPassword(
        Request $request,
        string $userId,
        string $password,
    ): IssueTemporaryPasswordCommand {
        return new IssueTemporaryPasswordCommand($request->attributes->get('principal'), $userId, $password, (string) $request->attributes->get('correlation_id'));
    }

    public static function revokeSessions(Request $request, string $userId): RevokeUserSessionsCommand
    {
        return new RevokeUserSessionsCommand($request->attributes->get('principal'), $userId, (string) $request->attributes->get('correlation_id'));
    }
}
