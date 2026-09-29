<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Mappers;

use Brick\Math\BigDecimal;
use Modules\Pricing\Domain\Enums\AmountRoundingMode;
use Modules\Pricing\Domain\Enums\CalculationMethod;
use Modules\Pricing\Domain\Enums\PricingBasis;
use Modules\Pricing\Domain\ValueObjects\TariffRuleDefinition;
use Modules\Pricing\Domain\ValueObjects\TariffRuleSelector;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffRateRuleRecord;

final class TariffRuleInput
{
    public static function fromRecord(TariffRateRuleRecord $rule): TariffRuleDefinition
    {
        return new TariffRuleDefinition(
            selector: new TariffRuleSelector($rule->service_offering_version_id, $rule->service_option_version_id,
                $rule->origin_zone_id, $rule->destination_zone_id, $rule->charge_type_id, (int) $rule->priority, PricingBasis::from($rule->basis)),
            method: CalculationMethod::from($rule->calculation_method),
            rangeFrom: $rule->range_from === null ? null : BigDecimal::of($rule->range_from),
            rangeTo: $rule->range_to === null ? null : BigDecimal::of($rule->range_to),
            fixedAmount: $rule->fixed_amount === null ? null : (int) $rule->fixed_amount,
            unitRate: $rule->unit_rate,
            percentageBps: $rule->percentage_bps === null ? null : (int) $rule->percentage_bps,
            minimumAmount: $rule->minimum_amount === null ? null : (int) $rule->minimum_amount,
            maximumAmount: $rule->maximum_amount === null ? null : (int) $rule->maximum_amount,
            roundingMode: AmountRoundingMode::from($rule->amount_rounding_mode),
            roundingStep: $rule->amount_rounding_step === null ? null : (int) $rule->amount_rounding_step,
        );
    }
}
