<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Dto;

use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;

final readonly class ManifestTargetChangeDto
{
    public function __construct(public ParcelRecord $updatedParcel, public ManifestRouteEvidenceDto $evidence) {}
}
