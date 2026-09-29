<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Consignment\Application\Repositories\OperationalStatusRepositoryInterface;
use Modules\Consignment\Infrastructure\Persistence\Models\OperationalStatusCatalogLockRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\StatusRecord;

final class EloquentOperationalStatusRepository implements OperationalStatusRepositoryInterface
{
    /** The single catalogue row every status write serializes on. */
    private const CATALOGUE_LOCK_ID = 1;

    public function visibleTo(?string $hqId): Collection
    {
        return StatusRecord::query()->visibleTo($hqId)->orderBy('sort_order')->orderBy('code')->get();
    }

    public function visibleCodes(?string $hqId, bool $manifestOnly): array
    {
        return StatusRecord::query()->visibleTo($hqId)
            ->when($manifestOnly, fn ($query) => $query->where('manifest_enabled', true))->pluck('code')->all();
    }

    public function lockById(string $statusId): ?StatusRecord
    {
        return StatusRecord::query()->where('status_id', $statusId)->lockForUpdate()->first();
    }

    public function codeTaken(string $code, ?string $hqId, bool $global): bool
    {
        return StatusRecord::query()->where('code', $code)->when(! $global, fn ($query) => $query->visibleTo($hqId))->exists();
    }

    public function lockCatalogue(): void
    {
        OperationalStatusCatalogLockRecord::query()->insertOrIgnore(['id' => self::CATALOGUE_LOCK_ID]);
        OperationalStatusCatalogLockRecord::query()->whereKey(self::CATALOGUE_LOCK_ID)->lockForUpdate()->firstOrFail();
    }
}
