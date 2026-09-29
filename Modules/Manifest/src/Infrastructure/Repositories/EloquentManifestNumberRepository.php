<?php

declare(strict_types=1);

namespace Modules\Manifest\Infrastructure\Repositories;

use DateTimeInterface;
use Modules\Manifest\Application\Repositories\ManifestNumberRepositoryInterface;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestNumberSequenceRecord;

final class EloquentManifestNumberRepository implements ManifestNumberRepositoryInterface
{
    public function lockSequence(string $sequenceKey, DateTimeInterface $at): ?ManifestNumberSequenceRecord
    {
        ManifestNumberSequenceRecord::query()->insertOrIgnore([
            'sequence_key' => $sequenceKey,
            'next_value' => 1,
            'updated_at' => $at,
        ]);

        return ManifestNumberSequenceRecord::query()->where('sequence_key', $sequenceKey)->lockForUpdate()->first();
    }
}
