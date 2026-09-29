<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Infrastructure\Repositories;

use Modules\ServiceCatalog\Application\Repositories\ScheduleChildrenRepositoryInterface;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleScopeRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleWindowRecord;

final class EloquentScheduleChildrenRepository implements ScheduleChildrenRepositoryInterface
{
    /** Rows written per statement, so a wide schedule never builds one oversized query. */
    private const BATCH_SIZE = 100;

    public function deleteWindows(string $versionId): void
    {
        CommitmentScheduleWindowRecord::query()->where('commitment_schedule_version_id', $versionId)->delete();
    }

    public function deleteScopes(string $versionId): void
    {
        CommitmentScheduleScopeRecord::query()->where('commitment_schedule_version_id', $versionId)->delete();
    }

    public function insertWindows(array $rows): void
    {
        $attributes = array_map(static fn (array $row): array => (new CommitmentScheduleWindowRecord)->forceFill($row)->getAttributes(), $rows);
        foreach (array_chunk($attributes, self::BATCH_SIZE) as $chunk) {
            CommitmentScheduleWindowRecord::query()->insert($chunk);
        }
    }

    public function insertScopes(array $rows): void
    {
        foreach (array_chunk($rows, self::BATCH_SIZE) as $chunk) {
            CommitmentScheduleScopeRecord::query()->insert($chunk);
        }
    }
}
