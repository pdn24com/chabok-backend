<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Geography\Application\Support\GeoJson;
use Modules\Operations\Application\Contracts\CoverageRuleWriterInterface;
use Modules\Operations\Application\Dto\CoverageRuleDto;
use Modules\Operations\Application\Repositories\CoverageRepositoryInterface;
use Modules\Operations\Domain\Enums\CoverageCriterionType;
use Modules\Operations\Infrastructure\Persistence\Models\CoverageRuleRecord;

final readonly class CoverageRuleWriter implements CoverageRuleWriterInterface
{
    public function __construct(
        private ClockInterface $clock,
        private CoverageRepositoryInterface $coverageRepository,
    ) {}

    /** @param list<CoverageRuleDto> $rules */
    public function replaceRules(string $hq, string $versionId, array $rules): void
    {
        $this->coverageRepository->deleteRules($versionId);
        $at = $this->clock->now();
        $rows = [];
        foreach ($rules as $rule) {
            $criterion = $rule->criterion;
            $type = $criterion->type;
            // Build stored rows through model casts before one bulk INSERT.
            $row = (new CoverageRuleRecord)->forceFill([
                'hq_id' => $hq, 'coverage_policy_version_id' => $versionId,
                'target' => $rule->target->value, 'target_node_id' => $rule->targetNodeId, 'priority' => $criterion->priority, 'offering_version_id' => $rule->offeringVersionId,
                'criterion_type' => $type->value,
                'province_id' => $type === CoverageCriterionType::PROVINCE ? $criterion->provinceId : null,
                'city_id' => $type === CoverageCriterionType::CITY ? $criterion->cityId : null,
                'postal_code_from' => $type === CoverageCriterionType::POSTAL_RANGE ? $criterion->postalFrom : null,
                'postal_code_to' => $type === CoverageCriterionType::POSTAL_RANGE ? $criterion->postalTo : null,
                'geometry_geojson' => $type === CoverageCriterionType::POLYGON ? GeoJson::serialize($criterion->geometry) : null,
                'center_latitude' => $type === CoverageCriterionType::POINT_RADIUS ? $criterion->center->latitude : null,
                'center_longitude' => $type === CoverageCriterionType::POINT_RADIUS ? $criterion->center->longitude : null,
                'radius_meters' => $type === CoverageCriterionType::POINT_RADIUS ? $criterion->radiusMeters : null,
                'created_at' => $at, 'updated_at' => $at,
            ]);
            $rows[] = $row->getAttributes();
        }
        $this->coverageRepository->insertRules($rows);
    }
}
