<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\AttachOperationalProfile;

use Illuminate\Database\ConnectionInterface;
use Modules\Iam\Application\Contracts\UserAccessGuardInterface;
use Modules\Iam\Application\Dto\DriverProfileSummaryDto;
use Modules\Iam\Application\Ports\InitialAssignmentWriterInterface;
use Modules\Iam\Application\Ports\OperationalProfileWriterInterface;
use Modules\Iam\Application\Ports\UserScopeAuthorizerInterface;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;

final readonly class AttachOperationalProfileHandler
{
    public function __construct(
        private UserAccessGuardInterface $userAccessGuard,
        private UserScopeAuthorizerInterface $userScopeAuthorizer,
        private ConnectionInterface $connection,
        private OperationalProfileWriterInterface $operationalProfileWriter,
        private InitialAssignmentWriterInterface $initialAssignmentWriter,
        private UserRepositoryInterface $userRepository,
    ) {}

    public function handle(AttachOperationalProfileCommand $command): ?DriverProfileSummaryDto
    {
        $actor = $command->actor;
        $userId = $command->userId;
        $input = $command->input;
        $correlationId = $command->correlationId;
        $hqId = $this->userAccessGuard->requireTenant($actor);
        $this->userScopeAuthorizer->assertTarget($actor, $userId, 'iam.users.manage');

        return $this->connection->transaction(function () use ($actor, $userId, $input, $correlationId, $hqId): ?DriverProfileSummaryDto {
            $user = $this->userRepository->lockByTenant($hqId, $userId);
            $this->userAccessGuard->assertTenantUser($user !== null, $user?->hq_id, $hqId);
            $assignment = $this->operationalProfileWriter->attach($actor, $userId, $input, $correlationId);
            if ($assignment !== null) {
                $this->initialAssignmentWriter->assign($hqId, $userId, $actor->userId, [$assignment], $correlationId);
            }

            return $this->operationalProfileWriter->forUser($actor, $userId);
        }, attempts: 3);
    }
}
