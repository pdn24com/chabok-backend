<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\UpdateOwnProfile;

use Illuminate\Database\ConnectionInterface;
use Modules\Foundation\Application\Ports\AuditWriterInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Iam\Application\Dto\UserAuditSnapshotDto;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

final readonly class UpdateOwnProfileHandler
{
    public function __construct(
        private ConnectionInterface $connection,
        private AuditWriterInterface $auditWriter,
        private UserRepositoryInterface $userRepository,
    ) {}

    public function handle(UpdateOwnProfileCommand $command): UserRecord
    {
        $actor = $command->actor;
        $input = $command->input;
        $correlationId = $command->correlationId;

        return $this->connection->transaction(function () use ($actor, $input, $correlationId): UserRecord {
            $before = $actor->hqId === null ? $this->userRepository->find($actor->userId) : $this->userRepository->lockByTenant($actor->hqId, $actor->userId);
            if ($before === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
            }
            $beforeSnapshot = UserAuditSnapshotDto::fromUser($before);
            $this->userRepository->apply($before, $input->attributes());
            $after = $before;
            $this->auditWriter->write($actor->hqId, $actor->userId, 'SELF_PROFILE_UPDATED', 'USER', $actor->userId, $correlationId, $beforeSnapshot, UserAuditSnapshotDto::fromUser($after));

            return $after;
        }, attempts: 3);
    }
}
