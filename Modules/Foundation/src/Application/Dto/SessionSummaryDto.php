<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Dto;

use DateTimeImmutable;

final readonly class SessionSummaryDto
{
    public function __construct(
        public string $sessionId,
        public ?string $deviceId,
        public ?string $deviceName,
        public DateTimeImmutable $issuedAt,
        public DateTimeImmutable $expiresAt,
        public DateTimeImmutable $lastSeenAt,
        public bool $revoked,
    ) {}
}
