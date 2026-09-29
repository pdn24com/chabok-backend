<?php

declare(strict_types=1);

namespace App\Infrastructure\Fixtures;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Modules\Audit\Infrastructure\Persistence\Models\AuditEventRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentPricingChargeLineRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentPricingVersionRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\ParcelRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\StatusEventRecord;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;
use Modules\Organization\Infrastructure\Persistence\Models\HqTenantRecord;
use Modules\Organization\Infrastructure\Persistence\Models\NodeRecord;

/**
 * Persistence for the local Consignment visual fixtures, including the history tables whose delete
 * triggers have to stand aside while a fixture set is rebuilt.
 */
final class LocalConsignmentFixtureStore
{
    /** Triggers that make Consignment history and audit rows immutable in normal operation. */
    private const IMMUTABLE_HISTORY_TABLES = ['consignment_pricing_versions', 'consignment_pricing_charge_lines', 'consignment_status_events'];

    private const CLEANUP_TRIGGERS = ['consignment_pricing_versions_immutable_delete', 'consignment_pricing_charge_lines_immutable_delete',
        'consignment_status_events_immutable_delete', 'audit_events_prevent_delete'];

    public function schemaReady(): bool
    {
        return Schema::hasTable('consignments') && Schema::hasTable('parcels');
    }

    public function manifestRowsExist(): bool
    {
        return Schema::hasTable('manifest_parcels');
    }

    public function tenantId(string $hqCode): string
    {
        return (string) HqTenantRecord::query()->where('hq_code', $hqCode)->value('hq_id');
    }

    public function nodeId(string $hqId, string $nodeCode): string
    {
        return (string) NodeRecord::query()->where('hq_id', $hqId)->where('node_code', $nodeCode)->value('node_id');
    }

    public function userId(string $hqId, string $normalizedUsername): string
    {
        return (string) UserRecord::query()->where('hq_id', $hqId)->where('normalized_username', $normalizedUsername)->value('user_id');
    }

    /** @param array<string, mixed> $changes */
    public function renameNode(string $nodeId, array $changes): void
    {
        NodeRecord::query()->where('node_id', $nodeId)->update($changes);
    }

    /** @param list<string> $numbers @return list<string> */
    public function existingNumbers(array $numbers): array
    {
        return ConsignmentRecord::query()->whereIn('consignment_number', $numbers)
            ->pluck('consignment_number')->map(static fn ($number): string => (string) $number)->all();
    }

    /** Fixture rows addressable by the current numbering or the retired prefix. @param list<string> $numbers @return Collection<int, ConsignmentRecord> */
    public function fixtureIdentities(array $numbers, string $legacyPrefix): Collection
    {
        return ConsignmentRecord::query()->where(function ($query) use ($numbers, $legacyPrefix): void {
            $query->where('consignment_number', 'like', $legacyPrefix.'%')->orWhereIn('consignment_number', $numbers);
        })->get(['consignment_id', 'consignment_number']);
    }

    /** Fixtures a Manifest already references, which must survive a cleanup. @param iterable<string> $consignmentIds @return array<string, true> */
    public function manifestReferencedConsignmentIds(iterable $consignmentIds): array
    {
        return ParcelRecord::query()
            ->whereHas('manifestRows', fn ($rows) => $rows->whereColumn('manifest_parcels.hq_id', 'parcels.hq_id'))
            ->whereIn('consignment_id', $consignmentIds)
            ->distinct()
            ->pluck('consignment_id')
            ->mapWithKeys(static fn ($id): array => [(string) $id => true])
            ->all();
    }

    /** Deletes a fixture set and its history, standing the immutability triggers aside for the duration. @param iterable<string> $consignmentIds */
    public function deleteFixtures(iterable $consignmentIds): int
    {
        $this->dropCleanupTriggers();
        try {
            return DB::transaction(function () use ($consignmentIds): int {
                AuditEventRecord::query()->where('target_type', 'CONSIGNMENT')->whereIn('target_id', $consignmentIds)->delete();
                $pricingIds = ConsignmentPricingVersionRecord::query()->whereIn('consignment_id', $consignmentIds)->pluck('pricing_version_id');
                ConsignmentPricingChargeLineRecord::query()->whereIn('pricing_version_id', $pricingIds)->delete();
                ConsignmentPricingVersionRecord::query()->whereIn('consignment_id', $consignmentIds)->delete();
                StatusEventRecord::query()->whereIn('consignment_id', $consignmentIds)->delete();
                ParcelRecord::query()->whereIn('consignment_id', $consignmentIds)->delete();

                return ConsignmentRecord::query()->whereIn('consignment_id', $consignmentIds)->delete();
            });
        } finally {
            $this->restoreCleanupTriggers();
        }
    }

    /** @param array<string, mixed> $attributes */
    public function insertConsignment(array $attributes): string
    {
        return (string) ConsignmentRecord::query()->insertGetId($attributes);
    }

    public function parcelId(string $consignmentId, string $number): string
    {
        return (string) ParcelRecord::query()->where('consignment_id', $consignmentId)->where('parcel_number', $number)->value('id');
    }

    public function insertParcel(array $attributes): void
    {
        ParcelRecord::query()->insert($attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function insertPricingVersion(array $attributes): string
    {
        return (string) ConsignmentPricingVersionRecord::query()->insertGetId($attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function insertPricingChargeLine(array $attributes): void
    {
        ConsignmentPricingChargeLineRecord::query()->insert($attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function insertStatusEvent(array $attributes): void
    {
        StatusEventRecord::query()->insert($attributes);
    }

    /** @param array<string, mixed> $attributes */
    public function insertAuditEvent(array $attributes): void
    {
        AuditEventRecord::query()->insert($attributes);
    }

    private function dropCleanupTriggers(): void
    {
        foreach (self::CLEANUP_TRIGGERS as $trigger) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$trigger}");
        }
    }

    private function restoreCleanupTriggers(): void
    {
        foreach (self::IMMUTABLE_HISTORY_TABLES as $table) {
            DB::unprepared("CREATE TRIGGER {$table}_immutable_delete BEFORE DELETE ON {$table} FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'immutable Consignment history'");
        }
        DB::unprepared("CREATE TRIGGER audit_events_prevent_delete BEFORE DELETE ON audit_events FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT = 'audit_events is append-only'");
    }
}
