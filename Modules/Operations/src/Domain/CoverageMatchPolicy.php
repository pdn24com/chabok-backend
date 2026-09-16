<?php

declare(strict_types=1);

namespace Modules\Operations\Domain;

use Modules\Geography\Domain\GeoJsonGeometry;

final readonly class CoverageMatchPolicy
{
    public function matches(object $rule, array $input): bool
    {
        return match ($rule->criterion_type) {
            'PROVINCE' => ($input['province_id'] ?? null) === $rule->province_id,
            'CITY' => ($input['city_id'] ?? null) === $rule->city_id,
            'POSTAL_RANGE' => isset($input['postal_code']) && strcmp($input['postal_code'], $rule->postal_code_from) >= 0 && strcmp($input['postal_code'], $rule->postal_code_to) <= 0,
            'POLYGON' => isset($input['latitude'], $input['longitude']) && GeoJsonGeometry::contains(json_decode($rule->geometry_geojson, true, 512, JSON_THROW_ON_ERROR), (float) $input['latitude'], (float) $input['longitude']),
            'POINT_RADIUS' => isset($input['latitude'], $input['longitude']) && GeoJsonGeometry::withinRadius((float) $input['latitude'], (float) $input['longitude'], (float) $rule->center_latitude, (float) $rule->center_longitude, (int) $rule->radius_meters),
            default => false,
        };
    }

    public function matchedEvidence(object $rule, array $input): array
    {
        $geography = match ($rule->criterion_type) {
            'PROVINCE' => ['province_id' => (string) $rule->province_id],
            'CITY' => [
                'province_id' => isset($input['province_id']) ? (string) $input['province_id'] : null,
                'city_id' => (string) $rule->city_id,
            ],
            default => null,
        };
        $postal = $rule->criterion_type === 'POSTAL_RANGE' ? [
            'postal_code' => (string) $input['postal_code'],
            'postal_code_from' => (string) $rule->postal_code_from,
            'postal_code_to' => (string) $rule->postal_code_to,
        ] : null;
        $geometry = match ($rule->criterion_type) {
            'POLYGON' => [
                'point' => ['latitude' => (float) $input['latitude'], 'longitude' => (float) $input['longitude']],
                'geometry' => json_decode((string) $rule->geometry_geojson, true, 512, JSON_THROW_ON_ERROR),
            ],
            'POINT_RADIUS' => [
                'point' => ['latitude' => (float) $input['latitude'], 'longitude' => (float) $input['longitude']],
                'center' => ['latitude' => (float) $rule->center_latitude, 'longitude' => (float) $rule->center_longitude],
                'radius_meters' => (int) $rule->radius_meters,
            ],
            default => null,
        };
        return ['geography' => $geography, 'postal' => $postal, 'geometry' => $geometry];
    }

    public function rank(object $rule): string
    {
        $specificity = ['PROVINCE' => 1, 'CITY' => 2, 'POSTAL_RANGE' => 3, 'POLYGON' => 4, 'POINT_RADIUS' => 4];
        return sprintf('%011d-%d', (int) $rule->priority + 100000, $specificity[$rule->criterion_type]);
    }
}
