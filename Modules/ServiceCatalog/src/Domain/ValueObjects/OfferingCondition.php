<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\ValueObjects;

use Modules\ServiceCatalog\Domain\Enums\EligibilityOperator;

/** Expected values belong to the configured fact vocabulary and may be scalar or a list. */
final readonly class OfferingCondition
{
    public function __construct(public string $factKey, public ?EligibilityOperator $operator, public mixed $expectedValue) {}
}
