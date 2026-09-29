<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\ValueObjects;

use Modules\ServiceCatalog\Domain\Enums\EligibilityOutcome;

final readonly class OfferingEligibilityDecision
{
    /** @param list<string> $reasonCodes @param list<string> $missingFacts */
    public function __construct(public EligibilityOutcome $outcome, public array $reasonCodes, public array $missingFacts, public string $evaluatedAt) {}
}
