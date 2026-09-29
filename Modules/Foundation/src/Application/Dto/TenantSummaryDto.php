<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Dto;

final readonly class TenantSummaryDto
{
    public function __construct(public string $hqId, public string $code, public string $title) {}
}
