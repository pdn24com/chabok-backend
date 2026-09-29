<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\ValueObjects;

final readonly class PricingValidationResult
{
    public bool $valid;

    /** @param list<PricingValidationIssue> $errors */
    public function __construct(public array $errors)
    {
        $this->valid = $errors === [];
    }
}
