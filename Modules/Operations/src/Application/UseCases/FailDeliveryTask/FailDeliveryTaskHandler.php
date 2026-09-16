<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\FailDeliveryTask;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class FailDeliveryTaskHandler
{
    public function __construct(private \Modules\Operations\Application\Services\DeliveryAccessGuard $deliveryAccessGuard)
    {
    }

    public function handle(FailDeliveryTaskCommand $command): FailDeliveryTaskResult
    {
        return new FailDeliveryTaskResult($this->execute($command->actor, $command->nodeId, $command->id, $command->expected, $command->reasonCode, $command->reason, $command->correlationId));
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
        $this->deliveryAccessGuard->executionAccess($actor, $nodeId, $id);
        throw new ApiException(ApiErrorCode::ExceptionReviewRequired, 422, 'Delivery failure must be submitted through a NOK Manifest Exception Review.');
    }
}
