<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Serialization;

use Modules\ServiceCatalog\Application\Dto\CommitmentDestinationDto;

final class CommitmentDestinationDocument
{
    public static function serialize(CommitmentDestinationDto $destination): array
    {
        $match = $destination->match;

        return [
            'zone_set_id' => $destination->zoneSetId,
            'zone_set_version_id' => $destination->zoneSetVersionId,
            'zone' => $match === null ? null : [
                'pricing_zone_id' => $match->zoneId, 'code' => $match->code, 'title' => $match->title,
                'rank' => $match->rank, 'remote_area' => $match->remoteArea,
            ],
            'match' => $match === null ? null : [
                'member_id' => $match->memberId, 'member_type' => $match->memberType, 'precedence' => $match->precedence,
            ],
        ];
    }
}
