<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\CreateUser;

use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\Clock;
use Modules\Foundation\Application\Contracts\IdentifierGenerator;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\User\Application\Contracts\IdentityProvisioner;
use Modules\User\Application\Contracts\InitialAssignmentWriter;
use Modules\User\Application\Contracts\UserAdministrationAuthorizer;
use Modules\User\Application\Repositories\UserRepository;
use Modules\User\Application\Data\UserData;
use Modules\User\Domain\IdentifierNormalizer;
use Modules\User\Domain\UserAdministrationPolicy;

final readonly class CreateUserHandler
{
    public function __construct(
        private UserAdministrationPolicy $policy,
        private UserAdministrationAuthorizer $authorizer,
        private IdentifierNormalizer $normalizer,
        private UserRepository $users,
        private TransactionManager $transactions,
        private IdentifierGenerator $ids,
        private Clock $clock,
        private IdentityProvisioner $identity,
        private \Modules\User\Application\Contracts\OperationalProfileWriter $operationalProfiles,
        private InitialAssignmentWriter $assignments,
        private \Modules\User\Application\Contracts\UserScopeAuthorizer $scopeAuthorizer,
        private AuditWriter $audit,
        private OutboxWriter $outbox,
    )
    {
    }

    public function handle(CreateUserCommand $command): CreateUserResult
    {
        return new CreateUserResult($this->execute($command->actor, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        $hqId = $this->policy->requireTenant($actor);
        $this->authorizer->assertCan($actor, 'iam.users.manage', $hqId);
        $mode = (string) $input['creation_mode'];
        $this->policy->assertCreationMode($mode, $input);
        $normalized = $this->normalizer->all($input['username'] ?? null, $input['mobile'] ?? null, $input['email'] ?? null);
        if ($this->users->identifiersExist(array_values(array_filter($normalized)))) {
            throw new ApiException(ApiErrorCode::Conflict, 409, 'An identifier is already in use.');
        }
        return $this->transactions->run(function () use ($actor, $input, $mode, $normalized, $hqId, $correlationId): array {
            $userId = $this->ids->uuid();
            $now = $this->clock->now();
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
                'display_name' => trim(($input['first_name'] ?? '') . ' ' . ($input['last_name'] ?? '')),
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
            if (isset($input['operational_profile'])) {
                $assignment = $this->operationalProfiles->attach($actor, $userId, $input['operational_profile'], $correlationId);
                if ($assignment !== null) {
                    $input['assignments'][] = $assignment;
                }
            }
            if ($input['assignments'] === []) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'At least one assignment is required.');
            }
            $this->assignments->assign($hqId, $userId, $actor->userId, $input['assignments'], $correlationId);
            $this->scopeAuthorizer->assertTarget($actor, $userId, 'iam.users.manage');
            $public = UserData::publicData($row);
            $this->audit->write($hqId, $actor->userId, 'USER_CREATED', 'USER', $userId, $correlationId, after: $public);
            $this->outbox->write($hqId, 'USER', $userId, 'iam.user.created', $correlationId, ['user_id' => $userId, 'status' => $row['status'], 'creation_mode' => $mode]);
            return $public;
        });
    }
}
