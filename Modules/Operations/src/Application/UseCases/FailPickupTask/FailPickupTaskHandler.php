<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\FailPickupTask;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class FailPickupTaskHandler
{
    public function __construct(private \Modules\Operations\Application\Services\PickupAccessGuard $pickupAccessGuard)
    {
    }

    public function handle(FailPickupTaskCommand $command): FailPickupTaskResult
    {
        return new FailPickupTaskResult($this->execute($command->actor, $command->nodeId, $command->id, $command->expected, $command->reasonCode, $command->reason, $command->correlationId));
    }

    private function execute(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $id,
        int $expected,
        string $reasonCode,
        string $reason,
        string $correlationId,
    ): array
    {
        $this->pickupAccessGuard->accessExecution($actor, $nodeId, $id);
        throw new ApiException(ApiErrorCode::ExceptionReviewRequired, 422, 'Pickup failure must be submitted through an NPU Manifest Exception Review.');
    }
}
