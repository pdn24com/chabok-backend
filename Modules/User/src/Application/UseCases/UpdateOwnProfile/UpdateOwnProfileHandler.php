<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\UpdateOwnProfile;

use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\Clock;
use Modules\Foundation\Application\Contracts\TransactionManager;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\User\Application\Repositories\UserRepository;
use Modules\User\Application\Data\UserData;

final readonly class UpdateOwnProfileHandler
{
    public function __construct(
        private TransactionManager $transactions,
        private Clock $clock,
        private UserRepository $users,
        private AuditWriter $audit,
    )
    {
    }

    public function handle(UpdateOwnProfileCommand $command): UpdateOwnProfileResult
    {
        return new UpdateOwnProfileResult($this->execute($command->actor, $command->input, $command->correlationId));
    }

    private function execute(AuthenticatedPrincipal $actor, array $input, string $correlationId): array
    {
        return $this->transactions->run(function () use ($actor, $input, $correlationId): array {
            $allowed = array_intersect_key($input, array_flip(['first_name', 'last_name', 'display_name']));
            $allowed['updated_at'] = $this->clock->now();
            $before = $actor->hqId === null ? $this->users->findById($actor->userId) : $this->users->findTenantUserForUpdate($actor->hqId, $actor->userId);
            if ($before === null) {
                throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
            }
            $this->users->update($actor->userId, $allowed);
            $after = $this->users->findById($actor->userId);
            $this->audit->write($actor->hqId, $actor->userId, 'SELF_PROFILE_UPDATED', 'USER', $actor->userId, $correlationId, UserData::publicData($before), UserData::publicData($after));
            return UserData::publicData($after);
        });
    }
}
