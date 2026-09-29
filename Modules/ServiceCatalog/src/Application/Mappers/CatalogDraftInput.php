<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Mappers;

use Modules\ServiceCatalog\Application\Dto\CatalogDraftDto;
use Modules\ServiceCatalog\Application\Dto\CommitmentScheduleDto;
use Modules\ServiceCatalog\Application\Dto\OfferingAvailabilityDto;
use Modules\ServiceCatalog\Application\Dto\OfferingCommitmentBindingDto;
use Modules\ServiceCatalog\Application\Dto\OfferingCoverageReferenceDto;
use Modules\ServiceCatalog\Application\Dto\OfferingEligibilityRuleDto;
use Modules\ServiceCatalog\Application\Dto\OfferingOptionRuleDto;
use Modules\ServiceCatalog\Application\Serialization\CatalogDraftDocument;

final class CatalogDraftInput
{
    public static function record(string $resource, array $input): CatalogDraftDto|CommitmentScheduleDto
    {
        $data = $resource === 'commitment-schedules' ? CommitmentScheduleDto::fromValidated($input) : self::draft($input);
        $data->sourceFingerprint = CatalogDraftDocument::inputFingerprint([...$input, 'valid_from' => null, 'valid_to' => null]);

        return $data;
    }

    public static function draft(array $input): CatalogDraftDto
    {
        return new CatalogDraftDto(
            code: $input['code'] ?? null,
            labels: $input['labels'] ?? [],
            description: $input['description'] ?? null,
            validFrom: $input['valid_from'] ?? null,
            validTo: $input['valid_to'] ?? null,
            definition: $input['definition'] ?? [],
            serviceTypeVersionId: $input['service_type_version_id'] ?? null,
            shippingMethodVersionId: $input['shipping_method_version_id'] ?? null,
            slaPolicy: $input['sla_policy'] ?? [],
            availabilitySummary: $input['availability_summary'] ?? [],
            expectedVersion: isset($input['expected_version']) ? (int) $input['expected_version'] : null,
            optionRules: array_map(self::option(...), $input['option_rules'] ?? []),
            eligibilityRules: array_map(self::eligibility(...), $input['eligibility_rules'] ?? []),
            coverageReferences: array_map(self::coverage(...), $input['coverage_references'] ?? []),
            availabilityBindings: array_map(self::availability(...), $input['availability_bindings'] ?? []),
            commitmentBinding: isset($input['commitment_binding']) ? self::commitment($input['commitment_binding']) : null,
            presentFields: array_keys($input),
        );
    }

    public static function option(array $input): OfferingOptionRuleDto
    {
        return new OfferingOptionRuleDto(
            serviceOptionVersionId: $input['service_option_version_id'] ?? '',
            compatibility: $input['compatibility'] ?? '',
            condition: $input['condition'] ?? null,
            presentFields: array_keys($input),
        );
    }

    public static function eligibility(array $input): OfferingEligibilityRuleDto
    {
        return new OfferingEligibilityRuleDto(
            dimension: $input['dimension'] ?? '',
            factKey: $input['fact_key'] ?? '',
            operator: $input['operator'] ?? 'EQ',
            expectedValue: $input['expected_value'] ?? null,
            reasonCode: $input['reason_code'] ?? '',
            priority: isset($input['priority']) ? (int) $input['priority'] : 100,
            presentFields: array_keys($input),
        );
    }

    public static function coverage(array $input): OfferingCoverageReferenceDto
    {
        return new OfferingCoverageReferenceDto(
            direction: $input['direction'] ?? 'BOTH',
            referenceType: $input['reference_type'] ?? '',
            referenceValue: $input['reference_value'] ?? '',
            secondaryReferenceValue: $input['secondary_reference_value'] ?? null,
            priority: isset($input['priority']) ? (int) $input['priority'] : 100,
            presentFields: array_keys($input),
        );
    }

    public static function availability(array $input): OfferingAvailabilityDto
    {
        return new OfferingAvailabilityDto(
            scopeType: $input['scope_type'] ?? '',
            scopeValue: $input['scope_value'] ?? null,
            enabled: (bool) ($input['enabled'] ?? true),
            presentFields: array_keys($input),
        );
    }

    public static function commitment(array $input): OfferingCommitmentBindingDto
    {
        return new OfferingCommitmentBindingDto(
            commitmentScheduleVersionId: $input['commitment_schedule_version_id'] ?? '',
            pickupMode: $input['pickup_mode'] ?? 'NONE',
            deliveryMode: $input['delivery_mode'] ?? 'NONE',
            durationValue: isset($input['duration_value']) ? (int) $input['duration_value'] : null,
            durationUnit: $input['duration_unit'] ?? null,
            durationAnchor: $input['duration_anchor'] ?? null,
            presentFields: array_keys($input),
        );
    }
}
