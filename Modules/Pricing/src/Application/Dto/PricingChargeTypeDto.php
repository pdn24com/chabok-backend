<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

use Modules\Pricing\Domain\Enums\ChargeCategory;

final readonly class PricingChargeTypeDto
{
    public function __construct(public string $code, public ChargeCategory $category, public string $accountingMappingKey,
        public bool $taxable = false, public bool $active = true) {}

    public static function fromInput(array $input): self
    {
        return new self($input['code'], ChargeCategory::from($input['category']), $input['accounting_mapping_key'],
            (bool) ($input['taxable'] ?? false), (bool) ($input['active'] ?? true));
    }
}
