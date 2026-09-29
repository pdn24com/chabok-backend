<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Dto;

final class ConsignmentChargeLineDto
{
    public function __construct(
        public string $chargeCode = '',
        public string $title = '',
        public int $amount = 0,
        public ?string $rateRuleId = null,
        public ?string $chargeTypeId = null,
        public ?string $category = null,
        public ?string $calculationMethod = null,
        public ?string $basis = null,
        public int|float|string|null $quantity = null,
        public int|float|string|null $unitRate = null,
        public array|string|null $explanation = null,
        public array $presentFields = [],
    ) {}
}
