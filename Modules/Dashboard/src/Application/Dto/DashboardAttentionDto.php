<?php

declare(strict_types=1);

namespace Modules\Dashboard\Application\Dto;

use DateTimeImmutable;

final readonly class DashboardAttentionDto
{
    public function __construct(public string $type, public string $entityId, public string $publicIdentifier, public DateTimeImmutable $occurredAt, public bool $actionAvailable) {}
}
