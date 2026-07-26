<?php

declare(strict_types=1);

namespace Modules\User\Application\Contracts;

interface UserAssignmentReader
{
    /** @return list<array<string, mixed>> */
    public function forUser(string $hqId, string $userId): array;
}
