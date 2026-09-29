<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\GetManifestException;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Manifest\Application\Contracts\ManifestAccessGuardInterface;
use Modules\Manifest\Application\Dto\ManifestExceptionStateDto;
use Modules\Manifest\Application\Repositories\ManifestRepositoryInterface;
use Modules\Operations\Application\Contracts\ManifestExceptionAccessInterface;

final readonly class GetManifestExceptionHandler
{
    public function __construct(
        private ManifestAccessGuardInterface $manifestAccessGuard,
        private ManifestExceptionAccessInterface $manifestExceptionAccess,
        private ManifestRepositoryInterface $manifestRepository,
    ) {}

    public function handle(GetManifestExceptionCommand $command): ManifestExceptionStateDto
    {
        $actor = $command->actor;
        $node = $command->nodeId;
        $id = $command->id;
        $this->manifestAccessGuard->access($actor, $node, 'manifest.view');
        $m = $this->manifestRepository->findAtNode($actor->hqId, $node, $id);
        if ($m === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }
        $cases = $this->manifestExceptionAccess->exceptionAttempts($actor->hqId, $id);

        return new ManifestExceptionStateDto($m, $cases);
    }
}
