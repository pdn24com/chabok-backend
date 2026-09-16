<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Services;

final readonly class OfferingChildrenWriter
{
    public function __construct(
        private \Modules\ServiceCatalog\Application\Services\OfferingReferenceGuard $offeringReferenceGuard,
        private \Modules\ServiceCatalog\Application\Repositories\CatalogRepository $catalog,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
    )
    {
    }

    public function replaceOfferingChildren(string $versionId, array $input, ?string $hqId): void
    {
        $this->offeringReferenceGuard->assertOfferingReferences($input, $hqId);
        $this->catalog->deleteOfferingChildren($versionId);
        foreach ((array) ($input['option_rules'] ?? []) as $rule) {
            $this->catalog->insertOptionRules([
                'offering_option_rule_id' => $this->identifiers->uuid(),
                'service_offering_version_id' => $versionId,
                'service_option_version_id' => $rule['service_option_version_id'],
                'compatibility' => $rule['compatibility'],
                'condition' => isset($rule['condition']) ? json_encode($rule['condition'], JSON_THROW_ON_ERROR) : null,
            ]);
        }
        foreach ((array) ($input['eligibility_rules'] ?? []) as $rule) {
            $this->catalog->insertEligibilityRules([
                'eligibility_rule_id' => $this->identifiers->uuid(),
                'service_offering_version_id' => $versionId,
                'dimension' => $rule['dimension'],
                'fact_key' => $rule['fact_key'],
                'operator' => $rule['operator'],
                'expected_value' => json_encode($rule['expected_value'], JSON_THROW_ON_ERROR),
                'reason_code' => $rule['reason_code'],
                'priority' => $rule['priority'] ?? 100,
            ]);
        }
        foreach ((array) ($input['coverage_references'] ?? []) as $reference) {
            $this->catalog->insertCoverageReferences([
                'coverage_reference_id' => $this->identifiers->uuid(),
                'service_offering_version_id' => $versionId,
                'direction' => $reference['direction'],
                'reference_type' => $reference['reference_type'],
                'reference_value' => $reference['reference_value'],
                'secondary_reference_value' => $reference['reference_type'] === 'POSTAL_RANGE' ? $reference['secondary_reference_value'] ?? null : null,
                'priority' => $reference['priority'] ?? 100,
            ]);
        }
        foreach ((array) ($input['availability_bindings'] ?? []) as $binding) {
            $scopeValue = match ($binding['scope_type']) {
                'TENANT' => $hqId,
                'PLATFORM' => null,
                default => $binding['scope_value'] ?? null,
            };
            $this->catalog->insertAvailabilityBindings([
                'availability_binding_id' => $this->identifiers->uuid(),
                'service_offering_version_id' => $versionId,
                'scope_type' => $binding['scope_type'],
                'scope_value' => $scopeValue,
                'enabled' => $binding['enabled'] ?? true,
            ]);
        }
        if (isset($input['commitment_binding']) && is_array($input['commitment_binding'])) {
            $binding = $input['commitment_binding'];
            $this->catalog->insertCommitmentBindings([
                'offering_commitment_binding_id' => $this->identifiers->uuid(),
                'service_offering_version_id' => $versionId,
                'commitment_schedule_version_id' => $binding['commitment_schedule_version_id'],
                'pickup_mode' => $binding['pickup_mode'],
                'delivery_mode' => $binding['delivery_mode'],
                'duration_value' => $binding['delivery_mode'] === 'COMPUTED' ? $binding['duration_value'] ?? null : null,
                'duration_unit' => $binding['delivery_mode'] === 'COMPUTED' ? $binding['duration_unit'] ?? null : null,
                'duration_anchor' => $binding['delivery_mode'] === 'COMPUTED' ? $binding['duration_anchor'] ?? null : null,
            ]);
        }
    }

    public function cloneOfferingChildren(string $from, string $to): void
    {
        foreach ($this->catalog->optionRules($from) as $row) {
            $copy = (array) $row;
            $copy['offering_option_rule_id'] = $this->identifiers->uuid();
            $copy['service_offering_version_id'] = $to;
            $this->catalog->insertOptionRules($copy);
        }
        foreach ($this->catalog->eligibilityRules($from) as $row) {
            $copy = (array) $row;
            $copy['eligibility_rule_id'] = $this->identifiers->uuid();
            $copy['service_offering_version_id'] = $to;
            $this->catalog->insertEligibilityRules($copy);
        }
        foreach ($this->catalog->coverageReferences($from) as $row) {
            $copy = (array) $row;
            $copy['coverage_reference_id'] = $this->identifiers->uuid();
            $copy['service_offering_version_id'] = $to;
            $this->catalog->insertCoverageReferences($copy);
        }
        foreach ($this->catalog->availabilityBindings($from) as $row) {
            $copy = (array) $row;
            $copy['availability_binding_id'] = $this->identifiers->uuid();
            $copy['service_offering_version_id'] = $to;
            $this->catalog->insertAvailabilityBindings($copy);
        }
        foreach ($this->catalog->commitmentBindings($from) as $row) {
            $copy = (array) $row;
            $copy['offering_commitment_binding_id'] = $this->identifiers->uuid();
            $copy['service_offering_version_id'] = $to;
            $this->catalog->insertCommitmentBindings($copy);
        }
    }
}
