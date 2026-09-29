<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\GetOwnProfile;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

final readonly class GetOwnProfileHandler
{
    public function __construct(
        private UserRepositoryInterface $userRepository,
    ) {}

    public function handle(GetOwnProfileCommand $command): UserRecord
    {
        $actor = $command->actor;
        $user = $this->userRepository->find($actor->userId);
        if ($user === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $user;
    }
}
