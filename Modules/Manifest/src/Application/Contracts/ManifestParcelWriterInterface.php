<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Contracts;

use Illuminate\Database\Eloquent\Collection;
use Modules\Manifest\Application\Dto\ManifestParcelAdditionDto;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestParcelRecord;

interface ManifestParcelWriterInterface
{
    /** @param Collection<int, ManifestParcelRecord> $rows */
    public function saveStates(Collection $rows): void;

    /** @param list<ManifestParcelAdditionDto> $additions */
    public function insert(array $additions): void;
}
