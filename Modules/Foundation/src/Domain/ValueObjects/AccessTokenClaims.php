<?php

declare(strict_types=1);

namespace Modules\Foundation\Domain\ValueObjects;

final readonly class AccessTokenClaims
{
    public function __construct(
        public string $userId,
        public string $sessionId,
        public ?string $hqId,
        public bool $mustChangePassword,
        public string $tokenId,
        public int $expiresAt,
    ) {}
}
