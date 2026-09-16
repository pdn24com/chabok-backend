<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\GetOwnProfile;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\User\Application\Repositories\UserRepository;
use Modules\User\Application\Data\UserData;

final readonly class GetOwnProfileHandler
{
    public function __construct(private UserRepository $users)
    {
    }

    public function handle(GetOwnProfileCommand $command): GetOwnProfileResult
    {
        return new GetOwnProfileResult($this->execute($command->actor));
    }

    private function execute(AuthenticatedPrincipal $actor): array
    {
        $user = $this->users->findById($actor->userId);
        if ($user === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        return UserData::publicData($user);
    }
}
