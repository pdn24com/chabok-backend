<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

/** Module-owned state for Manifest orchestration; the caller owns the transaction. */

interface ManifestExceptionAccess
{
    public function updateException(?string $exceptionCaseId, array $changes): void;

    public function lockLatestException(?string $hqId, ?string $id): ?object;

    public function exception(string $caseId): ?object;

    public function exceptionAttempts(?string $hqId, ?string $id): array;

    public function hasPendingException(?string $hqId, ?string $manifestId): bool;

    public function insertException(array $attributes): void;

    public function OrchestrationLockLatestException(?string $hqId, string $manifest): ?object;

    public function appendExceptionHistory(array $attributes): void;

    public function exceptionHistory(?string $exceptionCaseId): array;
}
