<?php

declare(strict_types=1);

namespace Modules\Iam\Infrastructure\Repositories;

use Modules\Iam\Application\Repositories\CredentialRepositoryInterface;
use Modules\Iam\Infrastructure\Persistence\Models\CredentialRecord;

final class EloquentCredentialRepository implements CredentialRepositoryInterface
{
    /** Columns a password rotation overwrites when the User already has a credential. */
    private const ROTATED_COLUMNS = ['password_hash', 'algorithm', 'algorithm_version', 'password_changed_at',
        'failed_attempt_count', 'locked_until', 'updated_at'];

    public function findForUser(string $userId): ?CredentialRecord
    {
        return CredentialRecord::query()->where('user_id', $userId)->first();
    }

    public function lockForUser(string $userId): ?CredentialRecord
    {
        return CredentialRecord::query()->where('user_id', $userId)->lockForUpdate()->first();
    }

    public function replaceForUser(array $attributes): void
    {
        CredentialRecord::query()->upsert([$attributes], ['user_id'], self::ROTATED_COLUMNS);
    }

    public function apply(CredentialRecord $credential, array $changes): void
    {
        $credential->forceFill($changes)->save();
    }
}
