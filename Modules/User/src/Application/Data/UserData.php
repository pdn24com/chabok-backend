<?php

declare(strict_types=1);

namespace Modules\User\Application\Data;

final class UserData
{
    public static function publicData(array $user): array
    {
        $public = array_intersect_key($user, array_flip([
            'user_id',
            'hq_id',
            'username',
            'mobile',
            'email',
            'first_name',
            'last_name',
            'display_name',
            'status',
            'must_change_password',
        ]));
        if (array_key_exists('must_change_password', $public)) {
            $public['must_change_password'] = (bool) $public['must_change_password'];
        }
        return $public;
    }
}
