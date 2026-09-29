<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Serialization;

use Modules\Geography\Application\Support\GeoJson;
use Modules\Geography\Domain\ValueObjects\GeoPoint;
use Modules\Operations\Application\UseCases\ResolveCoveragePolicy\ResolveCoveragePolicyResult;
use Modules\Operations\Domain\Enums\CoverageCriterionType;
use Modules\Operations\Domain\ValueObjects\CoverageLocation;

/** Stable persisted evidence document; domain matching does not depend on this shape. */
final class CoverageEvidence
{
    public static function location(CoverageLocation $location): array
    {
        $input = array_filter(['province_id' => $location->provinceId, 'city_id' => $location->cityId,
            'postal_code' => $location->postalCode], static fn ($value): bool => $value !== null);
        if ($location->point !== null) {
            $input += self::point($location->point);
        }

        return $input;
    }

    public static function geography(ResolveCoveragePolicyResult $result): ?array
    {
        return match ($result->criterion->type) {
            CoverageCriterionType::PROVINCE => ['province_id' => $result->criterion->provinceId],
            CoverageCriterionType::CITY => ['province_id' => $result->location->provinceId, 'city_id' => $result->criterion->cityId],
            default => null,
        };
    }

    public static function postal(ResolveCoveragePolicyResult $result): ?array
    {
        return $result->criterion->type === CoverageCriterionType::POSTAL_RANGE
            ? ['postal_code' => $result->location->postalCode, 'postal_code_from' => $result->criterion->postalFrom, 'postal_code_to' => $result->criterion->postalTo]
            : null;
    }

    public static function geometry(ResolveCoveragePolicyResult $result): ?array
    {
        return match ($result->criterion->type) {
            CoverageCriterionType::POLYGON => ['point' => self::point($result->location->point), 'geometry' => GeoJson::serialize($result->criterion->geometry)],
            CoverageCriterionType::POINT_RADIUS => ['point' => self::point($result->location->point), 'center' => self::point($result->criterion->center), 'radius_meters' => $result->criterion->radiusMeters],
            default => null,
        };
    }

    private static function point(GeoPoint $point): array
    {
        return ['latitude' => $point->latitude, 'longitude' => $point->longitude];
    }
}
