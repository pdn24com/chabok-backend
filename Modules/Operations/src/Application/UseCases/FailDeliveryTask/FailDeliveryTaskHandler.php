<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\FailDeliveryTask;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\DeliveryAccessGuardInterface;

final readonly class FailDeliveryTaskHandler
{
    public function __construct(private DeliveryAccessGuardInterface $deliveryAccessGuard) {}

    public function handle(FailDeliveryTaskCommand $command): never
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $id = $command->id;
        $this->deliveryAccessGuard->executionAccess($actor, $nodeId, $id);
        throw new ApiException(ApiErrorCode::ExceptionReviewRequired, 422, 'operations.delivery_failure_must_be_submitted_through_nok');
    }
}
