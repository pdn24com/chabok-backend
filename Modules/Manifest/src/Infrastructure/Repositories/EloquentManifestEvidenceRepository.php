<?php

declare(strict_types=1);

namespace Modules\Manifest\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Audit\Infrastructure\Persistence\Models\AuditEventRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\CustodyEventRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\StatusEventRecord;
use Modules\Manifest\Application\Repositories\ManifestEvidenceRepositoryInterface;

final class EloquentManifestEvidenceRepository implements ManifestEvidenceRepositoryInterface
{
    /** Relations a status timeline renders for each event. */
    private const TIMELINE_RELATIONS = ['consignment', 'parcel', 'node', 'initiator'];

    public function statusSequenceHeads(?string $hqId, array $consignmentIds): array
    {
        return StatusEventRecord::query()->where('hq_id', $hqId)->whereIn('consignment_id', $consignmentIds)
            ->selectRaw('consignment_id, MAX(event_sequence) AS sequence_head')->groupBy('consignment_id')
            ->pluck('sequence_head', 'consignment_id')->all();
    }

    public function custodySequenceHeads(?string $hqId, array $consignmentIds): array
    {
        return CustodyEventRecord::query()->where('hq_id', $hqId)->whereIn('consignment_id', $consignmentIds)
            ->selectRaw('consignment_id, MAX(event_sequence) AS sequence_head')->groupBy('consignment_id')
            ->pluck('sequence_head', 'consignment_id')->all();
    }

    public function insertStatusEvents(array $rows): void
    {
        StatusEventRecord::query()->insert($rows);
    }

    public function insertCustodyEvents(array $rows): void
    {
        CustodyEventRecord::query()->insert($rows);
    }

    public function statusHistory(?string $hqId, string $manifestId): Collection
    {
        return StatusEventRecord::query()->where(['hq_id' => $hqId, 'manifest_id' => $manifestId])
            ->whereHas('consignment')->with(self::TIMELINE_RELATIONS)->orderBy('event_sequence')->orderBy('created_at')->get();
    }

    public function auditTrail(?string $hqId, string $manifestId): Collection
    {
        return AuditEventRecord::query()->where(['hq_id' => $hqId, 'target_type' => 'MANIFEST', 'target_id' => $manifestId])
            ->with('initiator')->orderBy('created_at')->orderBy('audit_id')->get();
    }
}
