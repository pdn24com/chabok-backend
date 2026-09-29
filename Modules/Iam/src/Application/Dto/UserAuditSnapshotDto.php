<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Dto;

use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

final class UserAuditSnapshotDto
{
    public static function fromUser(UserRecord $user): array
    {
        return $user->only([
            'user_id', 'hq_id', 'username', 'mobile', 'email', 'first_name',
            'last_name', 'display_name', 'status', 'must_change_password',
        ]);
    }
}
