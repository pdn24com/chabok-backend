<?php

declare(strict_types=1);

namespace Modules\User\Application\Contracts;

interface UserInvitationReader
{
    public function latestStatus(string $userId): ?string;
}
