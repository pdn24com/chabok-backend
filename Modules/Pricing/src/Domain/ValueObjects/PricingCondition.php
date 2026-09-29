<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\ValueObjects;

use Modules\Pricing\Domain\Enums\PricingFact;

final readonly class PricingCondition
{
    public function __construct(public PricingFact $fact, public int|float|bool|string|null $expected) {}
}
