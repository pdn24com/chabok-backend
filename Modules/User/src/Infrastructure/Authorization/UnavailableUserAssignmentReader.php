<?php

declare(strict_types=1);

namespace Modules\User\Infrastructure\Authorization;

use Modules\User\Application\Contracts\UserAssignmentReader;

final class UnavailableUserAssignmentReader implements UserAssignmentReader
{
    public function forUser(string $hqId, string $userId): array
    {
        return [];
    }
}
