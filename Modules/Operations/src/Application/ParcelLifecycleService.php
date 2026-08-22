<?php

declare(strict_types=1);

namespace Modules\Operations\Application;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Modules\Foundation\Application\Contracts\AuditWriter;
use Modules\Foundation\Application\Contracts\OutboxWriter;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ParcelLifecycleService
{
    public function __construct(private AuditWriter $audit, private OutboxWriter $outbox) {}

    /** @return list<string> parcel IDs */
    public function transition(
        AuthenticatedPrincipal $actor,
        string $consignmentId,
        string $from,
        string $to,
        string $command,
        ?string $nodeId,
        string $custodyType,
        ?string $custodianId,
        string $correlationId,
        ?string $driverId = null,
        ?string $manifestId = null,
        ?string $reasonCode = null,
        ?string $safeNote = null,
        ?string $routePlanId = null,
        ?string $routePlanLegId = null,
        ?string $transportRunId = null,
    ): array {
        $parcels = DB::table('parcels')->where(['hq_id' => $actor->hqId, 'consignment_id' => $consignmentId])->lockForUpdate()->get();
        if ($parcels->isEmpty() || $parcels->contains(fn ($parcel): bool => (string) $parcel->current_status !== $from)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, "The {$from} to {$to} transition is not allowed.");
        }
        $ids = [];
        foreach ($parcels as $parcel) {
            DB::table('parcels')->where('parcel_id', $parcel->parcel_id)->update([
                'current_status' => $to, 'current_node_id' => $nodeId,
                'current_custody_type' => $custodyType, 'current_custodian_id' => $custodianId,
                'version' => (int) $parcel->version + 1, 'updated_at' => now(),
            ]);
            DB::table('consignment_status_events')->insert([
                'status_event_id' => (string) Str::uuid(), 'hq_id' => $actor->hqId,
                'consignment_id' => $consignmentId, 'parcel_id' => $parcel->parcel_id,
                'previous_status' => $from, 'new_status' => $to, 'initiator_id' => $actor->userId,
                'node_id' => $nodeId, 'driver_id' => $driverId, 'manifest_id' => $manifestId,
                'reason_code' => $reasonCode ?? $command, 'note' => $safeNote, 'created_at' => now(),
            ]);
            DB::table('parcel_custody_events')->insert([
                'custody_event_id' => (string) Str::uuid(), 'hq_id' => $actor->hqId,
                'consignment_id' => $consignmentId, 'parcel_id' => $parcel->parcel_id,
                'from_node_id' => $parcel->current_node_id, 'to_node_id' => $nodeId,
                'from_custody_type' => $parcel->current_custody_type,
                'to_custody_type' => $custodyType,
                'from_custodian_id' => $parcel->current_custodian_id,
                'to_custodian_id' => $custodianId, 'command_name' => $command,
                'initiator_id' => $actor->userId, 'manifest_id' => $manifestId,
                'route_plan_id' => $routePlanId, 'route_plan_leg_id' => $routePlanLegId,
                'transport_run_id' => $transportRunId, 'created_at' => now(),
            ]);
            $ids[] = (string) $parcel->parcel_id;
        }
        $consignment = DB::table('consignments')->where(['hq_id' => $actor->hqId, 'consignment_id' => $consignmentId])->lockForUpdate()->first();
        if ($consignment === null) throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        DB::table('consignments')->where('consignment_id', $consignmentId)->update(['current_status' => $to, 'updated_at' => now()]);
        if ((string) $consignment->current_status !== $to) {
            DB::table('consignment_status_events')->insert([
                'status_event_id' => (string) Str::uuid(), 'hq_id' => $actor->hqId,
                'consignment_id' => $consignmentId, 'parcel_id' => null,
                'previous_status' => $consignment->current_status, 'new_status' => $to,
                'initiator_id' => $actor->userId, 'node_id' => $nodeId, 'driver_id' => $driverId,
                'manifest_id' => $manifestId, 'reason_code' => $reasonCode ?? $command,
                'note' => $safeNote, 'created_at' => now(),
            ]);
        }
        $this->audit->write($actor->hqId, $actor->userId, $command, 'CONSIGNMENT', $consignmentId, $correlationId, ['status' => $from], ['status' => $to, 'custody_type' => $custodyType]);
        $this->outbox->write($actor->hqId, 'CONSIGNMENT', $consignmentId, 'operations.command.executed', $correlationId, [
            'command' => $command, 'resource_id' => $consignmentId, 'consignment_id' => $consignmentId, 'status' => $to,
        ]);
        return $ids;
    }
}
