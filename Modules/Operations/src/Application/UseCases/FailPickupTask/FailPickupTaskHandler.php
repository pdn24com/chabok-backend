<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\FailPickupTask;

use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\PickupAccessGuardInterface;

final readonly class FailPickupTaskHandler
{
    public function __construct(private PickupAccessGuardInterface $pickupAccessGuard) {}

    public function handle(FailPickupTaskCommand $command): never
    {
        $actor = $command->actor;
        $nodeId = $command->nodeId;
        $this->pickupAccessGuard->accessExecution($actor, $nodeId, $command->id);
        throw new ApiException(ApiErrorCode::ExceptionReviewRequired, 422, 'operations.pickup_failure_must_be_submitted_through_npu');
    }
}
