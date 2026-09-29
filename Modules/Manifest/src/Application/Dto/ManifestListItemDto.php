<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Dto;

use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;

final readonly class ManifestListItemDto
{
    public function __construct(public ManifestRecord $manifest, public ManifestCountsDto $counts, public ManifestContextSummaryDto $context) {}
}
