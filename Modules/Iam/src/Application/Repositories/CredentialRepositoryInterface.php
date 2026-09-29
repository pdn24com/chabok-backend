<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Repositories;

use Modules\Iam\Infrastructure\Persistence\Models\CredentialRecord;

interface CredentialRepositoryInterface
{
    public function findForUser(string $userId): ?CredentialRecord;

    public function lockForUser(string $userId): ?CredentialRecord;

    /** Replaces the single credential row a User owns; the unique `user_id` index makes this idempotent. @param array<string, mixed> $attributes */
    public function replaceForUser(array $attributes): void;

    /** @param array<string, mixed> $changes */
    public function apply(CredentialRecord $credential, array $changes): void;
}
