<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Serialization;

use Modules\Pricing\Application\Dto\ZoneResolutionDto;

final class ZoneDocument
{
    public static function zone(ZoneResolutionDto $resolution): array
    {
        return [
            'pricing_zone_id' => $resolution->zone->pricing_zone_id,
            'code' => $resolution->zone->code,
            'title' => $resolution->zone->title,
            'rank' => $resolution->zone->rank === null ? null : (int) $resolution->zone->rank,
            'remote_area' => (bool) $resolution->zone->remote_area,
        ];
    }

    public static function match(ZoneResolutionDto $resolution): array
    {
        return ['member_id' => $resolution->member->zone_member_id, 'member_type' => $resolution->member->member_type, 'precedence' => $resolution->precedence];
    }
}
