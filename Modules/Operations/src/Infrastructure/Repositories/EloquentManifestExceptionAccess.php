<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Foundation\Domain\Enums\ExceptionCaseStatus;
use Modules\Operations\Application\Contracts\ManifestExceptionAccessInterface;
use Modules\Operations\Infrastructure\Persistence\Models\OperationalExceptionCaseRecord;
use Modules\Operations\Infrastructure\Persistence\Models\OperationalExceptionHistoryRecord;

final class EloquentManifestExceptionAccess implements ManifestExceptionAccessInterface
{
    public function updateException(?string $exceptionCaseId, array $changes): void
    {
        OperationalExceptionCaseRecord::query()
            ->where('exception_case_id', $exceptionCaseId)
            ->update($changes);
    }

    public function lockLatestException(?string $hqId, ?string $id): ?OperationalExceptionCaseRecord
    {
        return OperationalExceptionCaseRecord::query()
            ->where(['hq_id' => $hqId, 'manifest_id' => $id])
            ->orderByDesc('submission_sequence')
            ->lockForUpdate()
            ->first();
    }

    public function exception(string $caseId): ?OperationalExceptionCaseRecord
    {
        return OperationalExceptionCaseRecord::query()
            ->where('exception_case_id', $caseId)->with(['history.actor', 'submitter', 'reviewer'])
            ->first();
    }

    public function exceptionAttempts(?string $hqId, ?string $id): Collection
    {
        return OperationalExceptionCaseRecord::query()
            ->where(['hq_id' => $hqId, 'manifest_id' => $id])
            ->orderByDesc('submission_sequence')
            ->with(['history.actor', 'submitter', 'reviewer'])->get();
    }

    public function hasPendingException(?string $hqId, ?string $manifestId): bool
    {
        return OperationalExceptionCaseRecord::query()
            ->where([
                'hq_id' => $hqId,
                'manifest_id' => $manifestId,
                'case_status' => ExceptionCaseStatus::Pending->value,
            ])
            ->exists();
    }

    public function insertException(array $attributes): string
    {
        return (string) OperationalExceptionCaseRecord::query()->forceCreate($attributes)->getKey();
    }

    public function appendExceptionHistory(array $attributes): void
    {
        (new OperationalExceptionHistoryRecord)->forceFill($attributes)->save();
    }
}
