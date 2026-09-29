<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Application\Contracts\DeliveryTaskReaderInterface;
use Modules\Operations\Application\Repositories\DeliveryTaskRepositoryInterface;
use Modules\Operations\Infrastructure\Persistence\Models\DeliveryTaskRecord;

final readonly class DeliveryTaskReader implements DeliveryTaskReaderInterface
{
    public function __construct(
        private DeliveryTaskRepositoryInterface $deliveryTaskRepository,
    ) {}

    public function locked(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
    ): DeliveryTaskRecord {
        $row = $this->deliveryTaskRepository->lockAtNode($actor->hqId, $nodeId, $id);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'common.resource_not_found');
        }

        return $row;
    }
}
