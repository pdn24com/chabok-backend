<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\ValueObjects;

use Modules\Pricing\Domain\Enums\MatrixValidationCode;
use Modules\Pricing\Domain\Enums\PricingValidationCode;

final readonly class PricingValidationIssue
{
    public function __construct(public PricingValidationCode|MatrixValidationCode $code, public string $field, public ?string $message = null) {}
}
