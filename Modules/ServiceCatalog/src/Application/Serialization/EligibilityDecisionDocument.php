<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Serialization;

use Modules\ServiceCatalog\Domain\ValueObjects\OfferingEligibilityDecision;

final class EligibilityDecisionDocument
{
    public static function make(OfferingEligibilityDecision $decision): array
    {
        return ['outcome' => $decision->outcome->value, 'reason_codes' => $decision->reasonCodes,
            'missing_facts' => $decision->missingFacts, 'evidence' => ['evaluated_at' => $decision->evaluatedAt]];
    }
}
