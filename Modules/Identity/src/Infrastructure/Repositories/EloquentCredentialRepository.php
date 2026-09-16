<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure\Repositories;

use Modules\Identity\Application\Repositories\CredentialRepository;
use Modules\Identity\Infrastructure\Persistence\Models\CredentialRecord;

final class EloquentCredentialRepository implements CredentialRepository
{
    public function findForUser(string $userId, bool $lock = false): ?\stdClass
    {
        $query = CredentialRecord::query()->where('user_id', $userId);
        if ($lock) {
            $query->lockForUpdate();
        }
        $row = $query->first();
        return $row === null ? null : (object) $row->getAttributes();
    }

    public function upsert(array $attributes): void
    {
        CredentialRecord::query()->upsert([$attributes], ['user_id'], [
            'password_hash',
            'algorithm',
            'algorithm_version',
            'password_changed_at',
            'failed_attempt_count',
            'locked_until',
            'updated_at',
        ]);
    }
}
