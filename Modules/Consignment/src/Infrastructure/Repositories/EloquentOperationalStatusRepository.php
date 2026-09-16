<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Repositories;

use Illuminate\Support\Facades\DB;
use Illuminate\Database\Query\Builder;
use Modules\Consignment\Application\Repositories\OperationalStatusRepository;
use Modules\Consignment\Infrastructure\Persistence\Models\StatusRecord;

final class EloquentOperationalStatusRepository implements OperationalStatusRepository
{
    private function visible(?string $hqId): Builder
    {
        return StatusRecord::query()->toBase()->where(fn($q) => $q->whereNull('hq_id')->when($hqId !== null, fn($q) => $q->orWhere('hq_id', $hqId)));
    }

    public function entries(?string $hqId): array
    {
        return $this->visible($hqId)->orderBy('sort_order')->orderBy('code')->get()->all();
    }

    public function codes(?string $hqId, bool $manifestOnly): array
    {
        return $this->visible($hqId)->when($manifestOnly, fn($q) => $q->where('manifest_enabled', true))->pluck('code')->all();
    }

    public function lockCatalog(): ?object
    {
        return DB::table('operational_status_catalog_lock')->where('id', 1)->lockForUpdate()->first();
    }

    public function lockStatus(string $id): ?object
    {
        return StatusRecord::query()->toBase()->where('status_id', $id)->lockForUpdate()->first();
    }

    public function codeExists(string $code, bool $global, ?string $hqId): bool
    {
        return StatusRecord::query()->toBase()->where('code', $code)->where(fn($q) => $global ? $q : $q->whereNull('hq_id')->orWhere('hq_id', $hqId))->exists();
    }

    public function update(string $id, array $attributes): void
    {
        StatusRecord::query()->where('status_id', $id)->update($attributes);
    }

    public function insert(array $attributes): void
    {
        StatusRecord::query()->insert($attributes);
    }

    public function appendRevision(array $attributes): void
    {
        DB::table('operational_status_revisions')->insert($attributes);
    }
}
