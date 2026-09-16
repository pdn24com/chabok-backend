<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

final readonly class CoverageRuleWriter
{
    public function __construct(
        private \Modules\Operations\Application\Repositories\CoveragePolicyRepository $coverage,
        private \Modules\Operations\Application\Services\CoverageRuleGuard $coverageRuleGuard,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
    )
    {
    }

    public function replaceRules(string $hq, string $versionId, array $rules): void
    {
        $this->coverage->deleteRules($versionId);
        foreach ($rules as $rule) {
            $criterion = $this->coverageRuleGuard->normalizeCriterion($rule['criterion']);
            $type = $criterion['criterion_type'];
            $this->coverage->insertRule([
                'coverage_rule_id' => $this->identifiers->uuid(),
                'hq_id' => $hq,
                'coverage_policy_version_id' => $versionId,
                'target' => $rule['target'],
                'target_node_id' => $rule['target_node_id'],
                'priority' => $rule['priority'],
                'offering_version_id' => $rule['offering_version_id'] ?? null,
                'criterion_type' => $type,
                'province_id' => $criterion['province_id'] ?? null,
                'city_id' => $criterion['city_id'] ?? null,
                'postal_code_from' => $criterion['postal_code_from'] ?? null,
                'postal_code_to' => $criterion['postal_code_to'] ?? null,
                'geometry_geojson' => isset($criterion['geometry']) ? json_encode($criterion['geometry'], JSON_THROW_ON_ERROR) : null,
                'center_latitude' => $criterion['center']['latitude'] ?? null,
                'center_longitude' => $criterion['center']['longitude'] ?? null,
                'radius_meters' => $criterion['radius_meters'] ?? null,
                'created_at' => $this->clock->now(),
                'updated_at' => $this->clock->now(),
            ]);
        }
    }
}
