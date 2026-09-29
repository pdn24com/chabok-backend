<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Dto;

use DateTimeImmutable;
use Modules\Foundation\Application\Dto\IssuedAccessTokenDto;

final readonly class SessionCredentialsDto
{
    public function __construct(public IssuedAccessTokenDto $accessToken, public string $refreshToken, public DateTimeImmutable $refreshExpiresAt) {}
}
