<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Contracts\PickupTaskReaderInterface;
use Modules\Operations\Application\Repositories\PickupTaskRepositoryInterface;
use Modules\Operations\Infrastructure\Persistence\Models\PickupTaskRecord;

final readonly class PickupTaskReader implements PickupTaskReaderInterface
{
    public function __construct(
        private PickupTaskRepositoryInterface $pickupTaskRepository,
    ) {}

    public function locked(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
    ): PickupTaskRecord {
        $row = $this->pickupTaskRepository->lockAtNode($actor->hqId, $nodeId, $id);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $row;
    }
}
