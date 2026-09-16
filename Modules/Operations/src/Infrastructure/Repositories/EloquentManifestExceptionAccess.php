<?php

declare(strict_types=1);

namespace Modules\Operations\Infrastructure\Repositories;

use Modules\Operations\Application\Contracts\ManifestExceptionAccess;
use Modules\Operations\Infrastructure\Persistence\Models\OperationalExceptionCaseRecord;
use Modules\Operations\Infrastructure\Persistence\Models\OperationalExceptionHistoryRecord;

final class EloquentManifestExceptionAccess implements ManifestExceptionAccess
{
    public function updateException(?string $exceptionCaseId, array $changes): void
    {
        OperationalExceptionCaseRecord::query()->toBase()->where('exception_case_id', $exceptionCaseId)->update($changes);
    }

    public function lockLatestException(?string $hqId, ?string $id): ?object
    {
        return OperationalExceptionCaseRecord::query()->toBase()->where(['hq_id' => $hqId, 'manifest_id' => $id])->orderByDesc('submission_sequence')->lockForUpdate()->first();
    }

    public function exception(string $caseId): ?object
    {
        return OperationalExceptionCaseRecord::query()->toBase()->where('exception_case_id', $caseId)->first();
    }

    public function exceptionAttempts(?string $hqId, ?string $id): array
    {
        return OperationalExceptionCaseRecord::query()->toBase()->where(['hq_id' => $hqId, 'manifest_id' => $id])->orderByDesc('submission_sequence')->get()->all();
    }

    public function hasPendingException(?string $hqId, ?string $manifestId): bool
    {
        return OperationalExceptionCaseRecord::query()->toBase()->where(['hq_id' => $hqId, 'manifest_id' => $manifestId, 'case_status' => 'PENDING'])->exists();
    }

    public function insertException(array $attributes): void
    {
        OperationalExceptionCaseRecord::query()->toBase()->insert($attributes);
    }

    public function OrchestrationLockLatestException(?string $hqId, string $manifest): ?object
    {
        return OperationalExceptionCaseRecord::query()->toBase()->where(['hq_id' => $hqId, 'manifest_id' => $manifest])->orderByDesc('submission_sequence')->lockForUpdate()->first();
    }

    public function appendExceptionHistory(array $attributes): void
    {
        OperationalExceptionHistoryRecord::query()->toBase()->insert($attributes);
    }

    public function exceptionHistory(?string $exceptionCaseId): array
    {
        return OperationalExceptionHistoryRecord::query()->toBase()->from('operational_exception_history as h')->join('users as u', 'u.user_id', '=', 'h.actor_id')->where('h.exception_case_id', $exceptionCaseId)->orderBy('h.created_at')->get()->all();
    }
}
