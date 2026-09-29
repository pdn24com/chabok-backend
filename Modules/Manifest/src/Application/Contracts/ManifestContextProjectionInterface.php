<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Contracts;

use Modules\Manifest\Application\Dto\ManifestContextSummaryDto;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestRecord;

interface ManifestContextProjectionInterface
{
    /** @param list<ManifestRecord> $manifests @return array<string, ManifestContextSummaryDto> */
    public function summaries(string $hq, array $manifests): array;
}
