<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

final readonly class CommitmentZoneSummaryDto
{
    public function __construct(public string $code, public string $title) {}
}
