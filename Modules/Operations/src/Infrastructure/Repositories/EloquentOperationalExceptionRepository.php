<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Repositories;

use Modules\Operations\Application\Repositories\OperationalExceptionRepositoryInterface;
use Modules\Operations\Infrastructure\Persistence\Models\OperationalExceptionCaseRecord;

final class EloquentOperationalExceptionRepository implements OperationalExceptionRepositoryInterface
{
    public function lockLatestForDeliveryTask(?string $hqId, string $deliveryTaskId, string $exceptionType): ?OperationalExceptionCaseRecord
    {
        return OperationalExceptionCaseRecord::query()
            ->where(['hq_id' => $hqId, 'delivery_task_id' => $deliveryTaskId, 'exception_type' => $exceptionType])
            ->orderByDesc('created_at')->lockForUpdate()->first();
    }

    public function update(string $exceptionCaseId, array $changes): void
    {
        OperationalExceptionCaseRecord::query()->where('exception_case_id', $exceptionCaseId)->update($changes);
    }
}
