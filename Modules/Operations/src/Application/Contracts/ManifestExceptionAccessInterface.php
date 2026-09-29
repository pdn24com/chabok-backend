<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Modules\Operations\Infrastructure\Persistence\Models\OperationalExceptionCaseRecord;

/** Module-owned state for Manifest orchestration; the caller owns the transaction. */
interface ManifestExceptionAccessInterface
{
    public function updateException(?string $exceptionCaseId, array $changes): void;

    public function lockLatestException(?string $hqId, ?string $id): ?OperationalExceptionCaseRecord;

    public function exception(string $caseId): ?OperationalExceptionCaseRecord;

    public function exceptionAttempts(?string $hqId, ?string $id): Collection;

    public function hasPendingException(?string $hqId, ?string $manifestId): bool;

    public function insertException(array $attributes): string;

    public function appendExceptionHistory(array $attributes): void;
}
