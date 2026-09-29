<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Dto;

use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestParcelRecord;

final readonly class ManifestEligibleParcelDto
{
    public function __construct(public ManifestParcelRecord $row, public ParcelRecord $parcel) {}
}
