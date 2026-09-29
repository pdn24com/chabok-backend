<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Serialization;

use Modules\Consignment\Application\Dto\ConsignmentChargeLineDto;
use Modules\Consignment\Application\Dto\ConsignmentPricingOptionDto;
use Modules\Consignment\Application\Dto\ConsignmentQuoteBundleDto;
use Modules\Consignment\Application\Dto\QuotedDeliveryWindowDto;

final class ConsignmentQuoteDocument
{
    public static function option(ConsignmentPricingOptionDto $input): array
    {
        $values = [
            'available' => $input->available,
            'external_method_code' => $input->externalMethodCode,
            'method_name' => $input->methodName,
            'icon' => $input->icon,
            'external_price_list_code' => $input->externalPriceListCode,
            'zone' => $input->zone,
            'currency' => $input->currency,
            'total_amount' => $input->totalAmount,
            'min_ins' => $input->minIns,
            'billable_weight_kg' => $input->billableWeightKg,
            'unavailable_reason' => $input->unavailableReason,
            'provider_code' => $input->providerCode,
            'internal_quote_id' => $input->internalQuoteId,
            'service_offering_id' => $input->serviceOfferingId,
            'service_offering_version_id' => $input->serviceOfferingVersionId,
            'service_type_id' => $input->serviceTypeId,
            'shipping_method_id' => $input->shippingMethodId,
            'result_fingerprint' => $input->resultFingerprint,
            'catalog_snapshot' => $input->catalogSnapshot,
            'commitment' => $input->commitment,
            'selected_option_version_ids' => $input->selectedOptionVersionIds,
            'warnings' => $input->warnings,
            'option_id' => $input->optionId,
            '_resolved_input_fingerprint' => $input->resolvedInputFingerprint,
            'charge_lines' => array_map(self::line(...), $input->chargeLines),
            'delivery_windows' => array_map(self::window(...), $input->deliveryWindows),
        ];

        return array_intersect_key($values, array_fill_keys($input->presentFields, true));
    }

    public static function line(ConsignmentChargeLineDto $input): array
    {
        $values = [
            'charge_code' => $input->chargeCode,
            'title' => $input->title,
            'amount' => $input->amount,
            'rate_rule_id' => $input->rateRuleId,
            'charge_type_id' => $input->chargeTypeId,
            'category' => $input->category,
            'calculation_method' => $input->calculationMethod,
            'basis' => $input->basis,
            'quantity' => $input->quantity,
            'unit_rate' => $input->unitRate,
            'explanation' => $input->explanation,
        ];

        return array_intersect_key($values, array_fill_keys($input->presentFields, true));
    }

    public static function window(QuotedDeliveryWindowDto $input): array
    {
        $values = [
            'gregorian_date' => $input->gregorianDate,
            'jalali_display_date' => $input->jalaliDisplayDate,
            'persian_weekday_label' => $input->persianWeekdayLabel,
            'persian_month_label' => $input->persianMonthLabel,
            'time_ranges' => $input->timeRanges,
        ];

        return array_intersect_key($values, array_fill_keys($input->presentFields, true));
    }

    public static function bundle(ConsignmentQuoteBundleDto $input): array
    {
        return [
            'quote_id' => $input->quoteId, 'quote_version' => $input->quoteVersion,
            'hq_id' => $input->hqId, 'node_id' => $input->nodeId, 'purpose' => $input->purpose,
            'input_fingerprint' => $input->inputFingerprint, 'consignment_id' => $input->consignmentId,
            'expected_version' => $input->expectedVersion, 'provider_calculated_at' => $input->providerCalculatedAt,
            'expires_at' => $input->expiresAt, 'options' => array_map(self::option(...), $input->options),
        ];
    }
}
