<?php

declare(strict_types=1);

namespace Modules\User\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\User\Application\Contracts\IdentityProvisioner;
use Modules\User\Application\Contracts\InitialAssignmentWriter;
use Modules\User\Application\Contracts\UserAdministrationAuthorizer;
use Modules\User\Application\Contracts\UserSessionManager;
use Modules\User\Application\Contracts\UserStore;
use Modules\User\Domain\IdentifierNormalizer;
use Modules\User\Domain\UserLifecyclePolicy;

final readonly class UserService
{
    public function __construct(
        private UserStore $users,
        private IdentifierNormalizer $normalizer,
        private UserLifecyclePolicy $lifecycle,
        private UserAdministrationAuthorizer $authorizer,
        private InitialAssignmentWriter $assignments,
        private IdentityProvisioner $identity,
        private UserSessionManager $sessions,
        private TransactionManager $transactions,
        private AuditWriter $audit,
        private OutboxWriter $outbox,
    ) {}

    /** @param array<string, mixed> $input */
    public function create(
        AuthenticatedPrincipal $actor,
        array $input,
        string $correlationId,
    ): array {
        $hqId = $this->requireTenant($actor);
        $this->authorizer->assertCan($actor, 'iam.users.manage', $hqId);
        $mode = (string) $input['creation_mode'];
        $this->assertCreationMode($mode, $input);
        $normalized = $this->normalizer->all(
            $input['username'] ?? null,
            $input['mobile'] ?? null,
            $input['email'] ?? null,
        );
        if ($this->users->identifiersExist(array_values(array_filter($normalized)))) {
            throw new ApiException(ApiErrorCode::Conflict, 409, 'An identifier is already in use.');
        }

        return $this->transactions->run(function () use ($actor, $input, $mode, $normalized, $hqId, $correlationId): array {
            $userId = (string) Str::uuid();
            $now = now();
            $row = [
                'user_id' => $userId,
                'hq_id' => $hqId,
                'username' => $input['username'] ?? null,
                'normalized_username' => $normalized['username'],
                'mobile' => $input['mobile'] ?? null,
                'normalized_mobile' => $normalized['mobile'],
                'email' => $input['email'] ?? null,
                'normalized_email' => $normalized['email'],
                'first_name' => $input['first_name'],
                'last_name' => $input['last_name'],
                'display_name' => trim(($input['first_name'] ?? '').' '.($input['last_name'] ?? '')),
                'status' => $mode === 'DIRECT_ACTIVE' ? 'ACTIVE' : 'INVITED',
                'must_change_password' => $mode === 'DIRECT_ACTIVE',
                'created_by' => $actor->userId,
                'activated_at' => $mode === 'DIRECT_ACTIVE' ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $this->users->insert($row);

            if ($mode === 'DIRECT_ACTIVE') {
                $this->identity->provisionPassword($userId, (string) $input['temporary_password']);
            } else {
                $channel = $mode === 'SMS_INVITATION' ? 'SMS' : 'EMAIL';
                $this->identity->createInvitation($row, $channel, $actor->userId, $correlationId);
            }
            $this->assignments->assign($hqId, $userId, $actor->userId, $input['assignments']);
            $public = $this->publicUser($row);
            $this->audit->write($hqId, $actor->userId, 'USER_CREATED', 'USER', $userId, $correlationId, after: $public);
            $this->outbox->write($hqId, 'USER', $userId, 'iam.user.created', $correlationId, [
                'user_id' => $userId, 'status' => $row['status'], 'creation_mode' => $mode,
            ]);

            return $public;
        });
    }

    public function list(
        AuthenticatedPrincipal $actor,
        int $page,
        int $pageSize,
        ?string $search,
        ?string $status,
    ): \Illuminate\Contracts\Pagination\LengthAwarePaginator {
        $hqId = $this->requireTenant($actor);
        $this->authorizer->assertCan($actor, 'iam.users.view', $hqId);

        return $this->users->paginate($hqId, $page, $pageSize, $search, $status);
    }

    /** @return array<string, mixed> */
    public function get(AuthenticatedPrincipal $actor, string $userId): array
    {
        $hqId = $this->requireTenant($actor);
        $this->authorizer->assertCan($actor, 'iam.users.view', $hqId);
        $user = $this->users->findById($userId);
        $this->assertTenantUser($user, $hqId);
        $invitation = DB::table('user_invitations')->where('user_id', $userId)
            ->orderByDesc('created_at')->value('status');

        return [
            'user' => $this->publicUser($user),
            'assignments' => [],
            'invitation_status' => $invitation,
            'sessions' => $this->sessions->listSessions($userId),
        ];
    }

    /** @param array<string, string> $input */
    public function update(
        AuthenticatedPrincipal $actor,
        string $userId,
        array $input,
        string $correlationId,
    ): array {
        $hqId = $this->requireTenant($actor);
        $this->authorizer->assertCan($actor, 'iam.users.manage', $hqId);

        return $this->transactions->run(function () use ($actor, $userId, $input, $hqId, $correlationId): array {
            $before = $this->users->findTenantUserForUpdate($hqId, $userId);
            $this->assertTenantUser($before, $hqId);
            $changes = array_intersect_key($input, array_flip(['first_name', 'last_name', 'display_name']));
            $changes['updated_at'] = now();
            $this->users->update($userId, $changes);
            $after = $this->users->findById($userId);
            $this->audit->write($hqId, $actor->userId, 'USER_PROFILE_UPDATED', 'USER', $userId, $correlationId, $this->publicUser($before), $this->publicUser($after));

            return $this->publicUser($after);
        });
    }

    public function updateSelf(
        AuthenticatedPrincipal $actor,
        array $input,
        string $correlationId,
    ): array {
        return $this->transactions->run(function () use ($actor, $input, $correlationId): array {
            $allowed = array_intersect_key($input, array_flip(['first_name', 'last_name', 'display_name']));
            $allowed['updated_at'] = now();
            $before = $actor->hqId === null
                ? $this->users->findById($actor->userId)
                : $this->users->findTenantUserForUpdate($actor->hqId, $actor->userId);
            if ($before === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            $this->users->update($actor->userId, $allowed);
            $after = $this->users->findById($actor->userId);
            $this->audit->write($actor->hqId, $actor->userId, 'SELF_PROFILE_UPDATED', 'USER', $actor->userId, $correlationId, $this->publicUser($before), $this->publicUser($after));

            return $this->publicUser($after);
        });
    }

    public function getSelf(AuthenticatedPrincipal $actor): array
    {
        $user = $this->users->findById($actor->userId);
        if ($user === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }

        return $this->publicUser($user);
    }

    /** @return array{user_id: string, status: string} */
    public function transition(
        AuthenticatedPrincipal $actor,
        string $userId,
        string $to,
        string $correlationId,
    ): array {
        $hqId = $this->requireTenant($actor);
        $this->authorizer->assertCan($actor, 'iam.users.manage', $hqId);

        return $this->transactions->run(function () use ($actor, $userId, $to, $hqId, $correlationId): array {
            $user = $this->users->findTenantUserForUpdate($hqId, $userId);
            $this->assertTenantUser($user, $hqId);
            $this->lifecycle->assertTransition((string) $user['status'], $to);
            $this->users->update($userId, [
                'status' => $to,
                'activated_at' => $to === 'ACTIVE' ? now() : $user['activated_at'],
                'updated_at' => now(),
            ]);
            if (in_array($to, ['SUSPENDED', 'DEACTIVATED'], true)) {
                $this->sessions->revokeUserSessions($userId, "USER_{$to}");
            }
            $this->audit->write($hqId, $actor->userId, "USER_{$to}", 'USER', $userId, $correlationId, ['status' => $user['status']], ['status' => $to]);
            $this->outbox->write($hqId, 'USER', $userId, 'iam.user.status_changed', $correlationId, [
                'user_id' => $userId, 'from' => $user['status'], 'to' => $to,
            ]);

            return ['user_id' => $userId, 'status' => $to];
        });
    }

    public function invite(
        AuthenticatedPrincipal $actor,
        string $userId,
        string $channel,
        string $correlationId,
    ): void {
        $hqId = $this->requireTenant($actor);
        $this->authorizer->assertCan($actor, 'iam.users.manage', $hqId);
        $this->transactions->run(function () use ($actor, $userId, $channel, $hqId, $correlationId): void {
            $user = $this->users->findTenantUserForUpdate($hqId, $userId);
            $this->assertTenantUser($user, $hqId);
            $this->identity->createInvitation($user, $channel, $actor->userId, $correlationId);
            $this->audit->write($hqId, $actor->userId, 'USER_INVITED', 'USER', $userId, $correlationId);
        });
    }

    public function temporaryPassword(
        AuthenticatedPrincipal $actor,
        string $userId,
        string $password,
        string $correlationId,
    ): void {
        $hqId = $this->requireTenant($actor);
        $this->authorizer->assertCan($actor, 'iam.users.manage', $hqId);
        $this->transactions->run(function () use ($actor, $userId, $password, $hqId, $correlationId): void {
            $user = $this->users->findTenantUserForUpdate($hqId, $userId);
            $this->assertTenantUser($user, $hqId);
            $this->identity->provisionPassword($userId, $password);
            $this->users->update($userId, ['must_change_password' => true, 'updated_at' => now()]);
            $this->sessions->revokeUserSessions($userId, 'TEMPORARY_PASSWORD_ISSUED');
            $this->audit->write($hqId, $actor->userId, 'TEMPORARY_PASSWORD_ISSUED', 'USER', $userId, $correlationId, safeNote: 'Caller-supplied temporary password accepted; secret not retained in audit.');
        });
    }

    public function revokeSessions(
        AuthenticatedPrincipal $actor,
        string $userId,
        string $correlationId,
    ): int {
        $hqId = $this->requireTenant($actor);
        $this->authorizer->assertCan($actor, 'iam.users.manage', $hqId);
        return $this->transactions->run(function () use ($actor, $userId, $hqId, $correlationId): int {
            $user = $this->users->findTenantUserForUpdate($hqId, $userId);
            $this->assertTenantUser($user, $hqId);
            $count = $this->sessions->revokeUserSessions($userId, 'ADMIN_REVOKED');
            $this->audit->write($hqId, $actor->userId, 'USER_SESSIONS_REVOKED', 'USER', $userId, $correlationId, safeNote: "{$count} sessions revoked.");

            return $count;
        });
    }

    private function assertCreationMode(string $mode, array $input): void
    {
        $hasPassword = isset($input['temporary_password']);
        $valid = match ($mode) {
            'DIRECT_ACTIVE' => $hasPassword,
            'SMS_INVITATION' => ! $hasPassword && ! empty($input['mobile']),
            'EMAIL_INVITATION' => ! $hasPassword && ! empty($input['email']),
            default => false,
        };
        if (! $valid) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The creation mode requirements are not satisfied.');
        }
    }

    private function requireTenant(AuthenticatedPrincipal $actor): string
    {
        if ($actor->hqId === null) {
            throw new ApiException(ApiErrorCode::Forbidden, 403, 'Access denied.');
        }

        return $actor->hqId;
    }

    private function assertTenantUser(?array $user, string $hqId): void
    {
        if ($user === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        if ($user['hq_id'] !== $hqId) {
            throw new ApiException(ApiErrorCode::Forbidden, 403, 'Access denied.');
        }
    }

    /** @param array<string, mixed> $user */
    public function publicUser(array $user): array
    {
        $public = array_intersect_key($user, array_flip([
            'user_id', 'hq_id', 'username', 'mobile', 'email', 'first_name',
            'last_name', 'display_name', 'status', 'must_change_password',
        ]));
        if (array_key_exists('must_change_password', $public)) {
            $public['must_change_password'] = (bool) $public['must_change_password'];
        }

        return $public;
    }
}
