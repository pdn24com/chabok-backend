<?php

declare(strict_types=1);

namespace Modules\Pricing\Application;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Pricing\Domain\FreightMatrices;

final readonly class TariffMatrixCompiler
{
    public function __construct(private FreightMatrices $matrices) {}

    public function prepare(array $input, array $zones, string $hqId): array
    {
        $policy = $input['zone_policy'] ?? 'DIRECTIONAL';
        $matrices = $input['freight_matrices'] ?? [];
        $errors = $this->matrices->validate($matrices, array_column($zones, 'pricing_zone_id'), $policy);
        if (! in_array($policy, ['DIRECTIONAL', 'HIGHER_ZONE_RANK'], true)) $errors[] = ['code' => 'PRICING_ZONE_POLICY_INVALID', 'field' => 'zone_policy'];
        if ($policy === 'HIGHER_ZONE_RANK' && ! $this->matrices->ranksValid($zones)) $errors[] = ['code' => 'PRICING_ZONE_RANK_INCOMPLETE', 'field' => 'zone_set_version_id'];
        $baseId = (string) DB::table('pricing_charge_types')->where('code', 'BASE_FREIGHT')->value('charge_type_id');
        foreach ($matrices as $matrix) {
            $offering = DB::table('service_offering_versions as v')->join('service_offerings as s', 's.service_offering_id', '=', 'v.service_offering_id')->where('v.service_offering_version_id', $matrix['service_offering_version_id'])->where(fn ($q) => $q->whereNull('s.hq_id')->orWhere('s.hq_id', $hqId))->where('v.status', 'PUBLISHED')->exists();
            if (! $offering) $errors[] = ['code' => 'PRICING_SERVICE_VERSION_NOT_PUBLISHED', 'field' => 'freight_matrices'];
            if (! empty($matrix['service_option_version_id']) && ! DB::table('service_offering_option_rules')->where(['service_offering_version_id' => $matrix['service_offering_version_id'], 'service_option_version_id' => $matrix['service_option_version_id']])->exists()) $errors[] = ['code' => 'PRICING_SERVICE_OPTION_NOT_BOUND', 'field' => 'freight_matrices'];
        }
        $rules = array_values(array_filter($input['rules'] ?? [], static fn ($r) => empty($r['matrix_cell_id'])));
        foreach ($rules as $rule) {
            if ($rule['charge_type_id'] !== $baseId) continue;
            if ($policy === 'HIGHER_ZONE_RANK') $errors[] = ['code' => 'PRICING_RANK_REQUIRES_MATRIX', 'field' => 'rules'];
            foreach ($matrices as $matrix) if ($matrix['service_offering_version_id'] === $rule['service_offering_version_id'] && ($matrix['service_option_version_id'] ?? null) === ($rule['service_option_version_id'] ?? null)) $errors[] = ['code' => 'PRICING_MATRIX_RULE_CONFLICT', 'field' => 'rules'];
        }
        if ($errors) throw new ApiException(ApiErrorCode::ValidationError, 422, 'Tariff matrix validation failed.', details: ['errors' => $errors]);
        return [...$input, 'zone_policy' => $policy, 'freight_matrices' => $matrices, 'rules' => [...$rules, ...$this->matrices->compile($matrices, $baseId)]];
    }
}
