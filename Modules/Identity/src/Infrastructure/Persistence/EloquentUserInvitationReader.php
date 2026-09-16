<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure\Persistence;

use Modules\Identity\Infrastructure\Persistence\Models\InvitationRecord;
use Modules\User\Application\Contracts\UserInvitationReader;

final class EloquentUserInvitationReader implements UserInvitationReader
{
    public function latestStatus(string $userId): ?string
    {
        return InvitationRecord::query()->where('user_id', $userId)->orderByDesc('created_at')->value('status');
    }
}
