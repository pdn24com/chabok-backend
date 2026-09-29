<?php

declare(strict_types=1);

namespace Modules\Manifest\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Collection as SupportCollection;
use Modules\Manifest\Application\Repositories\ManifestParcelRepositoryInterface;
use Modules\Manifest\Domain\Enums\ManifestParcelStatus;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestParcelRecord;

final class EloquentManifestParcelRepository implements ManifestParcelRepositoryInterface
{
    /** Rows written per statement, so a large manifest never builds one oversized query. */
    private const BATCH_SIZE = 100;

    /** Columns the processing outcome of a row overwrites. */
    private const OUTCOME_COLUMNS = ['manifest_parcel_status', 'failure_code', 'failure_reason', 'active_slot',
        'source_status', 'origin_node_id', 'destination_node_id', 'route_plan_id',
        'route_definition_version_id', 'route_plan_leg_id', 'route_definition_version_leg_id',
        'assigned_driver_id', 'assigned_vehicle_id', 'processed_at', 'evidence_recorded_at', 'updated_at'];

    public function insertUnlessSlotTaken(array $rows): bool
    {
        try {
            ManifestParcelRecord::query()->insert($rows);
        } catch (QueryException $exception) {
            $this->assertActiveSlotConflict($exception);

            return false;
        }

        return true;
    }

    public function saveUnlessSlotTaken(ManifestParcelRecord $row): bool
    {
        try {
            $row->save();
        } catch (QueryException $exception) {
            $this->assertActiveSlotConflict($exception);

            return false;
        }

        return true;
    }

    public function save(ManifestParcelRecord $row): void
    {
        $row->save();
    }

    public function saveDirty(Collection $rows): void
    {
        $changed = $rows->filter(static fn (ManifestParcelRecord $row): bool => $row->isDirty());
        foreach ($changed->chunk(self::BATCH_SIZE) as $batch) {
            ManifestParcelRecord::query()->upsert($batch->map->getAttributes()->all(), ['id'], self::OUTCOME_COLUMNS);
            foreach ($batch as $row) {
                $row->syncOriginal();
            }
        }
    }

    public function lockRowsWithStatus(?string $hqId, string $manifestId, array $statuses): Collection
    {
        return ManifestParcelRecord::query()->where(['hq_id' => $hqId, 'manifest_id' => $manifestId])
            ->whereIn('manifest_parcel_status', $statuses)->orderBy('parcel_id')->lockForUpdate()->get();
    }

    public function lockManifestRowsWithStatus(string $manifestId, array $statuses): Collection
    {
        return ManifestParcelRecord::query()->where('manifest_id', $manifestId)
            ->whereIn('manifest_parcel_status', $statuses)->orderBy('parcel_id')->lockForUpdate()->get();
    }

    public function lockAllRows(?string $hqId, string $manifestId): Collection
    {
        return ManifestParcelRecord::query()->where(['hq_id' => $hqId, 'manifest_id' => $manifestId])
            ->orderBy('parcel_id')->lockForUpdate()->get();
    }

    public function succeededSourceRows(?string $hqId, ?string $sourceManifestId, array $parcelIds): Collection
    {
        return ManifestParcelRecord::query()
            ->where(['hq_id' => $hqId, 'manifest_id' => $sourceManifestId, 'manifest_parcel_status' => ManifestParcelStatus::Succeeded->value])
            ->whereIn('parcel_id', $parcelIds)->get()->keyBy('parcel_id');
    }

    public function rowsWithConsignment(?string $hqId, string $manifestId): Collection
    {
        return ManifestParcelRecord::query()->where(['hq_id' => $hqId, 'manifest_id' => $manifestId])
            ->whereHas('parcel.consignment')->with('parcel.consignment')->orderBy('created_at')->get();
    }

    public function firstParcelOf(string $manifestId): ?object
    {
        return ManifestParcelRecord::query()->where('manifest_id', $manifestId)
            ->whereHas('parcel')->with('parcel')->orderBy('created_at')->first()?->parcel;
    }

    public function parcelIds(string $manifestId): array
    {
        return ManifestParcelRecord::query()->where('manifest_id', $manifestId)->pluck('parcel_id')->all();
    }

    public function hasRows(string $manifestId): bool
    {
        return ManifestParcelRecord::query()->where('manifest_id', $manifestId)->exists();
    }

    public function statusTotals(string $manifestId): array
    {
        return ManifestParcelRecord::query()->where('manifest_id', $manifestId)
            ->selectRaw('manifest_parcel_status, COUNT(*) AS total')->groupBy('manifest_parcel_status')
            ->pluck('total', 'manifest_parcel_status')->all();
    }

    public function statusTotalsByManifest(?string $hqId, array $manifestIds): SupportCollection
    {
        return ManifestParcelRecord::query()->where('hq_id', $hqId)->whereIn('manifest_id', $manifestIds)
            ->selectRaw('manifest_id, manifest_parcel_status, COUNT(*) AS total')
            ->groupBy('manifest_id', 'manifest_parcel_status')->get()->groupBy('manifest_id');
    }

    /** The unique active-slot index is the only conflict a caller may recover from. */
    private function assertActiveSlotConflict(QueryException $exception): void
    {
        $duplicateKey = in_array((int) ($exception->errorInfo[1] ?? 0), [19, 1062], true);
        if (! $duplicateKey || ! str_contains((string) ($exception->errorInfo[2] ?? ''), 'active_slot')) {
            throw $exception;
        }
    }
}
