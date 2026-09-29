<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Serialization;

use Modules\Manifest\Application\Dto\ManifestParcelOutcomeDto;

final class ManifestParcelOutcomeDocument
{
    public static function serialize(ManifestParcelOutcomeDto $outcome): array
    {
        $result = ['input' => $outcome->input, 'result' => $outcome->result->value];
        if ($outcome->parcelNumber !== null) {
            $result['parcel_number'] = $outcome->parcelNumber;
        }

        return [...$result, ...ManifestEligibilityDocument::metadata($outcome->reason)];
    }
}
