<?php

declare(strict_types=1);

namespace Modules\Manifest\Infrastructure\Repositories;

use Modules\Manifest\Application\Repositories\ManifestNumberRepository;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestNumberSequenceRecord;

final class EloquentManifestNumberRepository implements ManifestNumberRepository
{
    public function initialize(array $attributes): void
    {
        ManifestNumberSequenceRecord::query()->toBase()->insertOrIgnore($attributes);
    }

    public function lock(string $key): ?object
    {
        return ManifestNumberSequenceRecord::query()->toBase()->where('sequence_key', $key)->lockForUpdate()->first();
    }

    public function update(string $key, array $changes): void
    {
        ManifestNumberSequenceRecord::query()->toBase()->where('sequence_key', $key)->update($changes);
    }
}
