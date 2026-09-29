<?php

declare(strict_types=1);

namespace Modules\Iam\Infrastructure\Adapters;

use Modules\Foundation\Application\Ports\CommandActorLockInterface;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

final class UserCommandActorLock implements CommandActorLockInterface
{
    public function lock(string $actorId): void
    {
        UserRecord::query()->where('user_id', $actorId)->lockForUpdate()->first();
    }
}
