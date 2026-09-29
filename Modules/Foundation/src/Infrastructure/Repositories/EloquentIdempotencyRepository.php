<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Repositories;

use Modules\Foundation\Application\Repositories\IdempotencyRepositoryInterface;
use Modules\Foundation\Infrastructure\Persistence\Models\IdempotencyRecord;

final class EloquentIdempotencyRepository implements IdempotencyRepositoryInterface
{
    public function lockForCommand(string $actorId, string $commandName, string $key): ?IdempotencyRecord
    {
        return IdempotencyRecord::query()->where('actor_id', $actorId)->where('command_name', $commandName)
            ->where('idempotency_key', $key)->lockForUpdate()->first();
    }

    public function create(array $attributes): IdempotencyRecord
    {
        return IdempotencyRecord::query()->forceCreate($attributes);
    }

    public function apply(IdempotencyRecord $record, array $changes): void
    {
        $record->forceFill($changes)->save();
    }

    public function delete(IdempotencyRecord $record): void
    {
        $record->delete();
    }
}
