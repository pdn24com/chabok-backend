<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Dto;

use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;

final readonly class ManifestParcelTransitionDto
{
    public function __construct(public ParcelRecord $previousParcel, public ManifestRouteEvidenceDto $evidence) {}
}
