<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

final readonly class PricingZoneVersionSearchDto
{
    public function __construct(public string $search = '', public ?string $includeVersionId = null, public int $page = 1, public int $pageSize = 50) {}
}
