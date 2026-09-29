<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\CreateUser;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Application\Ports\OutboxWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Iam\Application\Contracts\IdentifierNormalizerInterface;
use Modules\Iam\Application\Contracts\UserAccessGuardInterface;
use Modules\Iam\Application\Contracts\UserIdentifierResolverInterface;
use Modules\Iam\Application\Dto\UserAuditSnapshotDto;
use Modules\Iam\Application\Ports\InitialAssignmentWriterInterface;
use Modules\Iam\Application\Ports\OperationalProfileWriterInterface;
use Modules\Iam\Application\Ports\UserAdministrationAuthorizerInterface;
use Modules\Iam\Application\Ports\UserScopeAuthorizerInterface;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;
use Modules\Iam\Application\UseCases\CreateInvitation\CreateInvitationCommand;
use Modules\Iam\Application\UseCases\CreateInvitation\CreateInvitationHandler;
use Modules\Iam\Application\UseCases\ProvisionPassword\ProvisionPasswordCommand;
use Modules\Iam\Application\UseCases\ProvisionPassword\ProvisionPasswordHandler;
use Modules\Iam\Domain\Enums\UserCreationMode;
use Modules\Iam\Domain\Enums\UserStatus;
use Modules\Iam\Domain\Policies\UserCreationPolicy;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

final readonly class CreateUserHandler
{
    public function __construct(
        private UserAccessGuardInterface $userAccessGuard,
        private UserCreationPolicy $userCreationPolicy,
        private UserAdministrationAuthorizerInterface $userAdministrationAuthorizer,
        private IdentifierNormalizerInterface $identifierNormalizer,
        private UserIdentifierResolverInterface $userIdentifierResolver,
        private ConnectionInterface $connection,
        private ClockInterface $clock,
        private ProvisionPasswordHandler $provisionPasswordHandler,
        private CreateInvitationHandler $createInvitationHandler,
        private OperationalProfileWriterInterface $operationalProfileWriter,
        private InitialAssignmentWriterInterface $initialAssignmentWriter,
        private UserScopeAuthorizerInterface $userScopeAuthorizer,
        private AuditWriterInterface $auditWriter,
        private OutboxWriterInterface $outboxWriter,
        private UserRepositoryInterface $userRepository,
    ) {}

    public function handle(CreateUserCommand $command): UserRecord
    {
        $actor = $command->actor;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $hqId = $this->userAccessGuard->requireTenant($actor);
        $this->userAdministrationAuthorizer->assertCan($actor, 'iam.users.manage', $hqId);
        $mode = $this->userCreationPolicy->requireValidMode($input->mode,
            $input->temporaryPassword !== null, ! empty($input->mobile), ! empty($input->email));
        $normalized = $this->identifierNormalizer->identifiers($input->username, $input->mobile, $input->email);
        if ($this->userIdentifierResolver->identifiersExist($normalized->values())) {
            throw new ApiException(ApiErrorCode::Conflict, 409, 'iam.identifier_is_already_use');
        }

        return $this->connection->transaction(function () use ($actor, $input, $mode, $normalized, $hqId, $correlationId): UserRecord {
            $now = $this->clock->now();
            $row = [
                'hq_id' => $hqId,
                'username' => $input->username,
                'normalized_username' => $normalized->username,
                'mobile' => $input->mobile,
                'normalized_mobile' => $normalized->mobile,
                'email' => $input->email,
                'normalized_email' => $normalized->email,
                'first_name' => $input->firstName,
                'last_name' => $input->lastName,
                'display_name' => trim($input->firstName.' '.$input->lastName),
                'status' => $mode === UserCreationMode::DirectActive ? UserStatus::Active->value : UserStatus::Invited->value,
                'must_change_password' => $mode === UserCreationMode::DirectActive,
                'created_by' => $actor->userId,
                'activated_at' => $mode === UserCreationMode::DirectActive ? $now : null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
            $user = $this->userRepository->create($row);
            $userId = (string) $user->getKey();
            if ($mode === UserCreationMode::DirectActive) {
                $this->provisionPasswordHandler->handle(new ProvisionPasswordCommand($userId, (string) $input->temporaryPassword));
            } else {
                $channel = $mode === UserCreationMode::SmsInvitation ? 'SMS' : 'EMAIL';
                $this->createInvitationHandler->handle(new CreateInvitationCommand($user, $channel, $actor->userId, $correlationId));
            }
            $assignments = $input->assignments;
            if ($input->operationalProfile !== null) {
                $assignment = $this->operationalProfileWriter->attach($actor, $userId, $input->operationalProfile, $correlationId);
                if ($assignment !== null) {
                    $assignments[] = $assignment;
                }
            }
            if ($assignments === []) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'iam.least_one_assignment_is_required');
            }
            $this->initialAssignmentWriter->assign($hqId, $userId, $actor->userId, $assignments, $correlationId);
            $this->userScopeAuthorizer->assertTarget($actor, $userId, 'iam.users.manage');
            $snapshot = UserAuditSnapshotDto::fromUser($user);
            $this->auditWriter->write($hqId, $actor->userId, 'USER_CREATED', 'USER', $userId, $correlationId, after: $snapshot);
            $this->outboxWriter->write($hqId, 'USER', $userId, 'iam.user.created', $correlationId, [
                'user_id' => $userId,
                'status' => $user->status,
                'creation_mode' => $mode->value,
            ]);

            return $user;
        }, attempts: 3);
    }
}
