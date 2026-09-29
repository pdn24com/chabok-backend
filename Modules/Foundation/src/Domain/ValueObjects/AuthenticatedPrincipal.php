<?php

declare(strict_types=1);

namespace Modules\Foundation\Domain\ValueObjects;

final readonly class AuthenticatedPrincipal
{
    public function __construct(
        public string $userId,
        public string $sessionId,
        public ?string $hqId,
        public bool $mustChangePassword,
    ) {}
}
