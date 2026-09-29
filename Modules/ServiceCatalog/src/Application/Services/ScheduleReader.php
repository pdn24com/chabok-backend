<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Application\Contracts\ScheduleReaderInterface;
use Modules\ServiceCatalog\Application\Repositories\CommitmentScheduleRepositoryInterface;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleVersionRecord;

final readonly class ScheduleReader implements ScheduleReaderInterface
{
    public function __construct(
        private CommitmentScheduleRepositoryInterface $commitmentScheduleRepository,
    ) {}

    public function versionDetail(AuthenticatedPrincipal $actor, string $versionId): CommitmentScheduleVersionRecord
    {
        $row = $this->commitmentScheduleRepository->findTenantVersion((string) $actor->hqId, $versionId);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $row;
    }
}
