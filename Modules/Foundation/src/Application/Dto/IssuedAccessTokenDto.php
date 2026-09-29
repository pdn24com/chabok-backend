<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Dto;

final readonly class IssuedAccessTokenDto
{
    public function __construct(public string $token, public int $expiresIn) {}
}
