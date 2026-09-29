<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Mappers;

use Modules\ServiceCatalog\Domain\Enums\EligibilityOperator;
use Modules\ServiceCatalog\Domain\ValueObjects\OfferingCondition;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceEligibilityRuleRecord;

final class OfferingConditionInput
{
    public static function fromArray(array $input): OfferingCondition
    {
        return new OfferingCondition((string) ($input['fact_key'] ?? ''), EligibilityOperator::tryFrom($input['operator'] ?? 'EQ'), $input['expected_value'] ?? null);
    }

    public static function fromRule(ServiceEligibilityRuleRecord $rule): OfferingCondition
    {
        return new OfferingCondition($rule->fact_key, EligibilityOperator::tryFrom($rule->operator), $rule->expected_value);
    }
}
