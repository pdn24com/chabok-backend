<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Dto;

use Modules\Geography\Application\Support\GeoJson;
use Modules\Geography\Domain\ValueObjects\GeoPoint;
use Modules\Operations\Application\Mappers\CoverageInput;
use Modules\Operations\Domain\Enums\CoverageCriterionType;
use Modules\Operations\Domain\Enums\CoverageTarget;
use Modules\Operations\Domain\ValueObjects\CoverageCriterion;
use Modules\Operations\Infrastructure\Persistence\Models\CoverageRuleRecord;

final readonly class CoverageRuleDto
{
    public function __construct(public CoverageTarget $target, public string $targetNodeId, public ?string $offeringVersionId, public CoverageCriterion $criterion) {}

    public static function fromValidated(array $input): self
    {
        $criterion = $input['criterion'];
        $type = CoverageCriterionType::from($criterion['criterion_type']);

        return new self(CoverageTarget::from($input['target']), $input['target_node_id'], $input['offering_version_id'] ?? null,
            new CoverageCriterion($type, (int) $input['priority'], $criterion['province_id'] ?? null, $criterion['city_id'] ?? null,
                $criterion['postal_code_from'] ?? null, $criterion['postal_code_to'] ?? null,
                $type === CoverageCriterionType::POLYGON && isset($criterion['geometry']) ? GeoJson::geometry($criterion['geometry']) : null,
                $type === CoverageCriterionType::POINT_RADIUS && isset($criterion['center']['latitude'], $criterion['center']['longitude'])
                    ? new GeoPoint((float) $criterion['center']['latitude'], (float) $criterion['center']['longitude']) : null,
                isset($criterion['radius_meters']) ? (int) $criterion['radius_meters'] : null));
    }

    public static function fromRecord(CoverageRuleRecord $rule): self
    {
        return new self(CoverageTarget::from($rule->target), $rule->target_node_id, $rule->offering_version_id, CoverageInput::criterion($rule));
    }
}
