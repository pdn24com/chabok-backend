<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Dto;

final readonly class DriverProfileSummaryDto
{
    public function __construct(public string $id, public string $code, public string $displayName, public string $homeNodeId, public string $status) {}
}
