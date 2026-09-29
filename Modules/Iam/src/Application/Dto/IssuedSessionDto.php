<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Dto;

use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

final readonly class IssuedSessionDto
{
    public function __construct(public string $sessionId, public UserRecord $user, public SessionCredentialsDto $credentials, public AccessContextDto $context) {}
}
