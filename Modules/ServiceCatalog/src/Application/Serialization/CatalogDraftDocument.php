<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Serialization;

use Modules\ServiceCatalog\Application\Dto\CatalogDraftDto;
use Modules\ServiceCatalog\Application\Dto\OfferingAvailabilityDto;
use Modules\ServiceCatalog\Application\Dto\OfferingCommitmentBindingDto;
use Modules\ServiceCatalog\Application\Dto\OfferingCoverageReferenceDto;
use Modules\ServiceCatalog\Application\Dto\OfferingEligibilityRuleDto;
use Modules\ServiceCatalog\Application\Dto\OfferingOptionRuleDto;

final class CatalogDraftDocument
{
    public static function draft(CatalogDraftDto $input): array
    {
        $values = [
            'code' => $input->code,
            'labels' => $input->labels,
            'description' => $input->description,
            'valid_from' => $input->validFrom,
            'valid_to' => $input->validTo,
            'definition' => $input->definition,
            'service_type_version_id' => $input->serviceTypeVersionId,
            'shipping_method_version_id' => $input->shippingMethodVersionId,
            'sla_policy' => $input->slaPolicy,
            'availability_summary' => $input->availabilitySummary,
            'expected_version' => $input->expectedVersion,
            'option_rules' => array_map(self::option(...), $input->optionRules),
            'eligibility_rules' => array_map(self::eligibility(...), $input->eligibilityRules),
            'coverage_references' => array_map(self::coverage(...), $input->coverageReferences),
            'availability_bindings' => array_map(self::availability(...), $input->availabilityBindings),
            'commitment_binding' => $input->commitmentBinding === null ? null : self::commitment($input->commitmentBinding),
        ];

        return array_intersect_key($values, array_fill_keys($input->presentFields, true));
    }

    public static function option(OfferingOptionRuleDto $input): array
    {
        $values = [
            'service_option_version_id' => $input->serviceOptionVersionId,
            'compatibility' => $input->compatibility,
            'condition' => $input->condition,
        ];

        return array_intersect_key($values, array_fill_keys($input->presentFields, true));
    }

    public static function eligibility(OfferingEligibilityRuleDto $input): array
    {
        $values = [
            'dimension' => $input->dimension,
            'fact_key' => $input->factKey,
            'operator' => $input->operator,
            'expected_value' => $input->expectedValue,
            'reason_code' => $input->reasonCode,
            'priority' => $input->priority,
        ];

        return array_intersect_key($values, array_fill_keys($input->presentFields, true));
    }

    public static function coverage(OfferingCoverageReferenceDto $input): array
    {
        $values = [
            'direction' => $input->direction,
            'reference_type' => $input->referenceType,
            'reference_value' => $input->referenceValue,
            'secondary_reference_value' => $input->secondaryReferenceValue,
            'priority' => $input->priority,
        ];

        return array_intersect_key($values, array_fill_keys($input->presentFields, true));
    }

    public static function availability(OfferingAvailabilityDto $input): array
    {
        $values = [
            'scope_type' => $input->scopeType,
            'scope_value' => $input->scopeValue,
            'enabled' => $input->enabled,
        ];

        return array_intersect_key($values, array_fill_keys($input->presentFields, true));
    }

    public static function commitment(OfferingCommitmentBindingDto $input): array
    {
        $values = [
            'commitment_schedule_version_id' => $input->commitmentScheduleVersionId,
            'pickup_mode' => $input->pickupMode,
            'delivery_mode' => $input->deliveryMode,
            'duration_value' => $input->durationValue,
            'duration_unit' => $input->durationUnit,
            'duration_anchor' => $input->durationAnchor,
        ];

        return array_intersect_key($values, array_fill_keys($input->presentFields, true));
    }

    /** Fingerprints the original validated wire values before DTO normalization. */
    public static function inputFingerprint(array $input): string
    {
        unset($input['expected_version'], $input['code']);

        return hash('sha256', json_encode(self::canonical($input), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE));
    }

    private static function canonical(array $value): array
    {
        if (! array_is_list($value)) {
            ksort($value);
        }
        foreach ($value as $key => $item) {
            if (is_array($item)) {
                $value[$key] = self::canonical($item);
            }
        }

        return $value;
    }
}
