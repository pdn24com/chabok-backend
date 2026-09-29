<?php

declare(strict_types=1);

namespace Modules\Dashboard\Application\Dto;

use DateTimeImmutable;

final readonly class DashboardUpdateDto
{
    public function __construct(public string $auditId, public string $action, public string $entityType, public string $entityId, public string $publicIdentifier, public DateTimeImmutable $occurredAt) {}
}
