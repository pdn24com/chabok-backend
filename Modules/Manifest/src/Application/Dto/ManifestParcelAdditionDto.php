<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Dto;

use Modules\Manifest\Infrastructure\Persistence\Models\ManifestParcelRecord;

final readonly class ManifestParcelAdditionDto
{
    public function __construct(public ManifestParcelRecord $record, public ManifestParcelOutcomeDto $outcome) {}
}
