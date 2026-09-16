<?php

declare(strict_types=1);

namespace Modules\Foundation\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Modules\Foundation\Application\Repositories\IdempotencyRepository;
use Modules\Foundation\Infrastructure\Persistence\Models\IdempotencyRecord;

final class EloquentIdempotencyRepository implements IdempotencyRepository
{
    public function lockActor(string $actorId): void
    {
        DB::table('users')->where('user_id', $actorId)->lockForUpdate()->first();
    }

    public function lockRecord(string $actorId, string $command, string $key): ?object
    {
        return IdempotencyRecord::query()->toBase()->where('actor_id', $actorId)->where('command_name', $command)->where('idempotency_key', $key)->lockForUpdate()->first();
    }

    public function insert(array $attributes): void
    {
        IdempotencyRecord::query()->toBase()->insert($attributes);
    }

    public function update(string $recordId, array $changes): void
    {
        IdempotencyRecord::query()->toBase()->where('record_id', $recordId)->update($changes);
    }

    public function delete(string $recordId): void
    {
        IdempotencyRecord::query()->toBase()->where('record_id', $recordId)->delete();
    }
}
