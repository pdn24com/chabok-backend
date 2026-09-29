<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Mappers;

use Modules\Geography\Application\Support\GeoJson;
use Modules\Geography\Domain\ValueObjects\GeoPoint;
use Modules\Operations\Domain\Enums\CoverageCriterionType;
use Modules\Operations\Domain\ValueObjects\CoverageCriterion;
use Modules\Operations\Domain\ValueObjects\CoverageLocation;
use Modules\Operations\Infrastructure\Persistence\Models\CoverageRuleRecord;

final class CoverageInput
{
    public static function location(array $input): CoverageLocation
    {
        return CoverageLocation::fromCoordinates($input['province_id'] ?? null, $input['city_id'] ?? null, $input['postal_code'] ?? null,
            isset($input['latitude']) ? (float) $input['latitude'] : null,
            isset($input['longitude']) ? (float) $input['longitude'] : null);
    }

    public static function criterion(CoverageRuleRecord $rule): CoverageCriterion
    {
        $type = CoverageCriterionType::from($rule->criterion_type);

        return new CoverageCriterion($type, $rule->priority, $rule->province_id, $rule->city_id,
            $rule->postal_code_from, $rule->postal_code_to,
            $type === CoverageCriterionType::POLYGON ? GeoJson::geometry($rule->geometry_geojson) : null,
            $type === CoverageCriterionType::POINT_RADIUS ? new GeoPoint((float) $rule->center_latitude, (float) $rule->center_longitude) : null,
            $rule->radius_meters);
    }
}
