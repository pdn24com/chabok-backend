<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Contracts;

use Modules\Iam\Application\Dto\IssuedSessionDto;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

interface SessionIssuerInterface
{
    public function createSession(
        UserRecord $user,
        ?string $deviceId,
        ?string $deviceName,
        string $correlationId,
        ?string $ip,
        ?string $userAgent,
    ): IssuedSessionDto;
}
