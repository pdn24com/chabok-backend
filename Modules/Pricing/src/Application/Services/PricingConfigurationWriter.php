<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

final readonly class PricingConfigurationWriter
{
    public function __construct(
        private \Modules\Pricing\Application\Repositories\PricingRepository $pricing,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
    )
    {
    }

    public function replaceRules(string $versionId, array $rules): void
    {
        $this->pricing->deleteRules($versionId);
        foreach ($rules as $rule) {
            $this->pricing->insertRateRule([
                'rate_rule_id' => $this->identifiers->uuid(),
                'matrix_cell_id' => $rule['matrix_cell_id'] ?? null,
                'taxable' => $rule['taxable'] ?? null,
                'tariff_version_id' => $versionId,
                'service_offering_version_id' => $rule['service_offering_version_id'],
                'service_option_version_id' => $rule['service_option_version_id'] ?? null,
                'charge_type_id' => $rule['charge_type_id'],
                'origin_zone_id' => $rule['origin_zone_id'] ?? null,
                'destination_zone_id' => $rule['destination_zone_id'] ?? null,
                'calculation_method' => $rule['calculation_method'],
                'basis' => $rule['basis'] ?? 'BILLABLE_WEIGHT',
                'range_from' => $rule['range_from'] ?? null,
                'range_to' => $rule['range_to'] ?? null,
                'fixed_amount' => $rule['fixed_amount'] ?? null,
                'unit_rate' => $rule['unit_rate'] ?? null,
                'incremental_step_kg' => $rule['incremental_step_kg'] ?? null,
                'incremental_step' => $rule['incremental_step'] ?? null,
                'percentage_bps' => $rule['percentage_bps'] ?? null,
                'minimum_amount' => $rule['minimum_amount'] ?? null,
                'maximum_amount' => $rule['maximum_amount'] ?? null,
                'amount_rounding_mode' => $rule['amount_rounding_mode'] ?? 'NONE',
                'amount_rounding_step' => $rule['amount_rounding_step'] ?? null,
                'basis_charge_codes' => isset($rule['basis_charge_codes']) ? json_encode($rule['basis_charge_codes'], JSON_THROW_ON_ERROR) : null,
                'conditions' => isset($rule['conditions']) ? json_encode($rule['conditions'], JSON_THROW_ON_ERROR) : null,
                'priority' => $rule['priority'] ?? 100,
            ]);
        }
    }

    public function insertLines(string $parentId, array $lines): void
    {
        foreach ($lines as $index => $line) {
            $this->pricing->insertQuoteLine([
                'quote_line_id' => $this->identifiers->uuid(),
                'quote_id' => $parentId,
                'line_number' => $index + 1,
                'charge_type_id' => $line['charge_type_id'],
                'rate_rule_id' => $line['rate_rule_id'],
                'charge_type_code' => $line['charge_code'],
                'title' => $line['title'],
                'calculation_method' => $line['calculation_method'],
                'basis' => $line['basis'],
                'quantity' => $line['quantity'],
                'unit_rate' => $line['unit_rate'],
                'amount' => $line['amount'],
                'accounting_mapping_key' => $line['accounting_mapping_key'],
                'explanation' => json_encode($line['explanation'], JSON_THROW_ON_ERROR),
            ]);
        }
    }
}
