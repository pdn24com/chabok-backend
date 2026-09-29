<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Mappers;

use Modules\Consignment\Application\Dto\ConsignmentChargeLineDto;
use Modules\Consignment\Application\Dto\ConsignmentPricingOptionDto;
use Modules\Consignment\Application\Dto\ConsignmentQuoteBundleDto;
use Modules\Consignment\Application\Dto\QuotedDeliveryWindowDto;
use Modules\Foundation\Domain\Enums\Currency;
use UnexpectedValueException;

/** Converts external provider and encrypted cache documents at their boundary. */
final class ConsignmentQuoteMapper
{
    public static function option(array $input): ConsignmentPricingOptionDto
    {
        return new ConsignmentPricingOptionDto(
            available: $input['available'] ?? false,
            externalMethodCode: $input['external_method_code'] ?? '',
            methodName: $input['method_name'] ?? '',
            icon: $input['icon'] ?? null,
            externalPriceListCode: $input['external_price_list_code'] ?? null,
            zone: $input['zone'] ?? null,
            currency: $input['currency'] ?? Currency::Irr->value,
            totalAmount: $input['total_amount'] ?? null,
            minIns: $input['min_ins'] ?? null,
            billableWeightKg: $input['billable_weight_kg'] ?? null,
            unavailableReason: $input['unavailable_reason'] ?? null,
            providerCode: $input['provider_code'] ?? null,
            internalQuoteId: $input['internal_quote_id'] ?? null,
            serviceOfferingId: $input['service_offering_id'] ?? null,
            serviceOfferingVersionId: $input['service_offering_version_id'] ?? null,
            serviceTypeId: $input['service_type_id'] ?? null,
            shippingMethodId: $input['shipping_method_id'] ?? null,
            resultFingerprint: $input['result_fingerprint'] ?? null,
            catalogSnapshot: $input['catalog_snapshot'] ?? null,
            commitment: $input['commitment'] ?? null,
            selectedOptionVersionIds: $input['selected_option_version_ids'] ?? [],
            warnings: $input['warnings'] ?? [],
            optionId: $input['option_id'] ?? null,
            resolvedInputFingerprint: $input['_resolved_input_fingerprint'] ?? null,
            chargeLines: array_map(self::line(...), $input['charge_lines'] ?? []),
            deliveryWindows: array_map(self::window(...), $input['delivery_windows'] ?? []),
            presentFields: array_keys($input),
        );
    }

    public static function line(array $input): ConsignmentChargeLineDto
    {
        return new ConsignmentChargeLineDto(
            chargeCode: $input['charge_code'] ?? '',
            title: $input['title'] ?? '',
            amount: $input['amount'] ?? 0,
            rateRuleId: $input['rate_rule_id'] ?? null,
            chargeTypeId: $input['charge_type_id'] ?? null,
            category: $input['category'] ?? null,
            calculationMethod: $input['calculation_method'] ?? null,
            basis: $input['basis'] ?? null,
            quantity: $input['quantity'] ?? null,
            unitRate: $input['unit_rate'] ?? null,
            explanation: $input['explanation'] ?? null,
            presentFields: array_keys($input),
        );
    }

    public static function window(array $input): QuotedDeliveryWindowDto
    {
        return new QuotedDeliveryWindowDto(
            gregorianDate: $input['gregorian_date'] ?? null,
            jalaliDisplayDate: $input['jalali_display_date'] ?? null,
            persianWeekdayLabel: $input['persian_weekday_label'] ?? null,
            persianMonthLabel: $input['persian_month_label'] ?? null,
            timeRanges: $input['time_ranges'] ?? [],
            presentFields: array_keys($input),
        );
    }

    public static function bundle(array $input): ConsignmentQuoteBundleDto
    {
        foreach (['quote_id', 'quote_version', 'hq_id', 'node_id', 'purpose', 'input_fingerprint', 'provider_calculated_at', 'expires_at', 'options'] as $key) {
            if (! isset($input[$key])) {
                throw new UnexpectedValueException('Invalid stored quote bundle.');
            }
        }

        return new ConsignmentQuoteBundleDto(
            (string) $input['quote_id'], (int) $input['quote_version'], (string) $input['hq_id'], (string) $input['node_id'],
            (string) $input['purpose'], (string) $input['input_fingerprint'], $input['consignment_id'] ?? null, $input['expected_version'] ?? null,
            (string) $input['provider_calculated_at'], (string) $input['expires_at'], array_map(self::option(...), $input['options']),
        );
    }
}
