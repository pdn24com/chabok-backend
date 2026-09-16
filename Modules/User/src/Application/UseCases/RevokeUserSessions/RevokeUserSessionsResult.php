<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\RevokeUserSessions;

final readonly class RevokeUserSessionsResult
{
    public function __construct(public int $data)
    {
    }
}
