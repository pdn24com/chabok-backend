<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Repositories;

use Modules\Operations\Infrastructure\Persistence\Models\OperationalExceptionCaseRecord;

interface OperationalExceptionRepositoryInterface
{
    /** The newest open case of a kind for one delivery task, locked before its resolution is recorded. */
    public function lockLatestForDeliveryTask(?string $hqId, string $deliveryTaskId, string $exceptionType): ?OperationalExceptionCaseRecord;

    /** @param array<string, mixed> $changes */
    public function update(string $exceptionCaseId, array $changes): void;
}
