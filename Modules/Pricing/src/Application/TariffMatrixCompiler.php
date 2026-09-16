<?php

declare(strict_types=1);

namespace Modules\Pricing\Application;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Pricing\Domain\FreightMatrices;

final readonly class TariffMatrixCompiler
{
    public const GLOBAL_COLUMN = '00000000-0000-4000-8000-000000000000';

    public function __construct(
        private \Modules\ServiceCatalog\Application\Contracts\CatalogResolver $currentCatalog,
        private FreightMatrices $matrices,
        private \Modules\Pricing\Application\Repositories\TariffMatrixRepository $repository,
    )
    {
    }

    public function prepare(array $input, array $zones, string $hqId): array
    {
        if (($input['tariff_kind'] ?? 'FREIGHT') === 'SERVICE') {
            return $this->prepareService($input, $zones);
        }
        $policy = $input['zone_policy'] ?? 'DIRECTIONAL';
        $matrices = $input['freight_matrices'] ?? [];
        $errors = $this->matrices->validate($matrices, array_column($zones, 'pricing_zone_id'), $policy);
        if (!in_array($policy, ['DIRECTIONAL', 'HIGHER_ZONE_RANK'], true)) {
            $errors[] = ['code' => 'PRICING_ZONE_POLICY_INVALID', 'field' => 'zone_policy'];
        }
        if ($policy === 'HIGHER_ZONE_RANK' && !$this->matrices->ranksValid($zones)) {
            $errors[] = ['code' => 'PRICING_ZONE_RANK_INCOMPLETE', 'field' => 'zone_set_version_id'];
        }
        $baseId = (string) $this->repository->baseFreightChargeId();
        foreach ($matrices as $matrix) {
            try {
                $this->currentCatalog->resolve('offerings', (string) $matrix['service_offering_version_id'], $hqId);
                $offering = true;
            } catch (ApiException) {
                $offering = false;
            }
            if (!$offering) {
                $errors[] = ['code' => 'PRICING_SERVICE_VERSION_NOT_PUBLISHED', 'field' => 'freight_matrices'];
            }
            if (!empty($matrix['service_option_version_id']) && !$this->currentCatalog->optionBound($matrix['service_offering_version_id'], $matrix['service_option_version_id'], $hqId)) {
                $errors[] = ['code' => 'PRICING_SERVICE_OPTION_NOT_BOUND', 'field' => 'freight_matrices'];
            }
        }
        $rules = array_values(array_filter($input['rules'] ?? [], static fn($r) => empty($r['matrix_cell_id'])));
        foreach ($rules as $rule) {
            if ($rule['charge_type_id'] !== $baseId) {
                continue;
            }
            if ($policy === 'HIGHER_ZONE_RANK') {
                $errors[] = ['code' => 'PRICING_RANK_REQUIRES_MATRIX', 'field' => 'rules'];
            }
            foreach ($matrices as $matrix) {
                if ($matrix['service_offering_version_id'] === $rule['service_offering_version_id'] && ($matrix['service_option_version_id'] ?? null) === ($rule['service_option_version_id'] ?? null)) {
                    $errors[] = ['code' => 'PRICING_MATRIX_RULE_CONFLICT', 'field' => 'rules'];
                }
            }
        }
        if ($errors) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Tariff matrix validation failed.', details: ['errors' => $errors]);
        }
        return [
            ...$input,
            'zone_policy' => $policy,
            'freight_matrices' => $matrices,
            'rules' => [...$rules, ...$this->matrices->compile($matrices, $baseId)],
        ];
    }

    private function prepareService(array $input, array $zones): array
    {
        $charge = $this->repository->activeCharge($input['service_charge_type_id'] ?? null);
        if (!$charge || $charge->category !== 'SURCHARGE') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'نوع هزینهٔ خدمات معتبر انتخاب کنید.');
        }
        $basis = $input['matrix_basis'] ?? 'DECLARED_VALUE';
        if (!in_array($basis, ['ACTUAL_WEIGHT', 'BILLABLE_WEIGHT', 'PARCEL_COUNT', 'DECLARED_VALUE', 'COD_AMOUNT'], true)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'مبنای ماتریس معتبر نیست.');
        }
        $global = empty($input['zone_set_version_id']);
        $policy = $global ? 'HIGHER_ZONE_RANK' : $input['zone_policy'] ?? 'DIRECTIONAL';
        $matrices = $input['freight_matrices'] ?? [];
        $zoneIds = $global ? [self::GLOBAL_COLUMN] : array_column($zones, 'pricing_zone_id');
        foreach ($matrices as &$matrix) {
            $matrix['service_offering_version_id'] = null;
            $matrix['service_option_version_id'] = null;
        }
        unset($matrix);
        $errors = $this->matrices->validate($matrices, $zoneIds, $policy);
        if (!$global && $policy === 'HIGHER_ZONE_RANK' && !$this->matrices->ranksValid($zones)) {
            $errors[] = ['code' => 'PRICING_ZONE_RANK_INCOMPLETE', 'field' => 'zone_set_version_id'];
        }
        if (!empty($input['service_tariff_family_ids']) || !empty($input['rules']) || !empty($input['is_default'])) {
            $errors[] = ['code' => 'PRICING_SERVICE_COMPOSITION_INVALID', 'field' => 'rules'];
        }
        if ($errors) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'ماتریس خدمات معتبر نیست.', details: ['errors' => $errors]);
        }
        $rules = $this->matrices->compile($matrices, (string) $charge->charge_type_id);
        foreach ($rules as &$rule) {
            $rule['basis'] = $basis;
            if ($global) {
                $rule['destination_zone_id'] = null;
            }
            if (isset($rule['incremental_step_kg'])) {
                $rule['incremental_step'] = $rule['incremental_step_kg'];
                unset($rule['incremental_step_kg']);
            }
            $rule['priority'] = 10;
            $rule['conditions'] = match ($charge->code) {
                'INSURANCE', 'INSURANCE_FEE' => ['insurance_enabled' => true],
                'COD_FEE' => ['cod_enabled' => true],
                default => [],
            };
        }
        unset($rule);
        return [
            ...$input,
            'zone_policy' => $policy,
            'matrix_basis' => $basis,
            'freight_matrices' => $matrices,
            'rules' => $rules,
        ];
    }
}
