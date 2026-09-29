<?php

declare(strict_types=1);

namespace Modules\Geography\Domain\Enums;

enum GeometryFailure
{
    /** Key into lang/<locale>/api.php. */
    public function messageKey(): string
    {
        return match ($this) {
            self::INVALID => 'geography.geojson_geometry_is_invalid',
            self::RING_NOT_CLOSED => 'geography.polygon_rings_must_be_closed',
            self::SELF_INTERSECTION => 'geography.polygon_rings_cannot_self_intersect',
            self::INVALID_TOPOLOGY => 'geography.polygon_topology_is_invalid',
        };
    }
    case INVALID;
    case RING_NOT_CLOSED;
    case SELF_INTERSECTION;
    case INVALID_TOPOLOGY;
}
