<?php

declare(strict_types=1);

namespace Modules\CrmTask\Application\Dto;

/** One colleague who sat in a meeting, and for how many minutes. */
final readonly class ActivityParticipantDto
{
    public function __construct(
        public string $userId,
        public int $minutes,
    ) {}
}
