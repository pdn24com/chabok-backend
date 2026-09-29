<?php

declare(strict_types=1);

namespace Modules\Dashboard\Application\Dto;

final readonly class ManifestCountsDto
{
    public function __construct(public int $total, public int $draft, public int $open, public int $closed, public int $inbound, public int $outbound, public int $delivery, public int $failedRows) {}
}
