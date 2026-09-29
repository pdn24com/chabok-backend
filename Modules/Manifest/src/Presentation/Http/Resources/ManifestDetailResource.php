<?php

declare(strict_types=1);

namespace Modules\Manifest\Presentation\Http\Resources;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Audit\Infrastructure\Persistence\Models\AuditEventRecord;
use Modules\Consignment\Infrastructure\Persistence\Models\StatusEventRecord;
use Modules\Manifest\Application\Serialization\ManifestEligibilityDocument;
use Modules\Manifest\Application\Serialization\ManifestTimestamp;
use Modules\Manifest\Application\Serialization\ManifestWorkflowDocument;
use Modules\Manifest\Domain\Enums\ManifestEligibilityReason;

final class ManifestDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $manifest = $this->list->manifest;
        $base = (new ManifestListResource($this->list))->resolve($request);
        $rows = $this->parcels;
        $eligibilities = $this->eligibilities;
        $parcels = array_map(fn ($row): array => [
            'manifest_parcel_id' => (string) $row->manifest_parcel_id,
            'parcel_id' => (string) $row->parcel_id,
            'parcel_number' => (string) $row->parcel->parcel_number,
            'consignment_id' => (string) $row->parcel->consignment->consignment_id,
            'consignment_number' => (string) $row->parcel->consignment->consignment_number,
            'receiver_contact_name' => (string) $row->parcel->consignment->receiver_contact_name,
            'current_status' => (string) $row->parcel->current_status,
            'manifest_parcel_status' => (string) $row->manifest_parcel_status,
            'failure_code' => $row->failure_code,
            'failure_reason' => $row->failure_reason,
            'eligibility' => $row->failure_code !== null ? ManifestEligibilityDocument::metadata((string) $row->failure_code) : ($row->manifest_parcel_status === 'SUCCEEDED' ? ManifestEligibilityDocument::metadata(ManifestEligibilityReason::Eligible) : ManifestEligibilityDocument::metadata($eligibilities[$row->parcel_id])),
            'input_source' => (string) $row->input_source,
            'input_value' => (string) $row->input_value,
            'processed_at' => $row->processed_at ? ManifestTimestamp::format($row->processed_at) : null,
            'created_at' => ManifestTimestamp::format($row->created_at),
        ], $rows->all());

        return [
            ...$base,
            'created_by' => (string) $manifest->created_by,
            'approved_by' => $manifest->approved_by,
            'closed_at' => $manifest->closed_at ? ManifestTimestamp::format($manifest->closed_at) : null,
            'parcels' => $parcels,
            'bucket_counts' => (new ManifestCountsResource($this->list->counts))->resolve($request),
            'permitted_actions' => $this->permittedActions,
            'status_events' => $this->statusEvents($this->resource->statusEvents),
            'custody_events' => (new ManifestWorkflowDocument)->custodyEvents($this->custodyEvents),
            'movement_evidence' => (new ManifestWorkflowDocument)->movementEvidence($manifest),
            'exception_state' => $this->exceptionState === null ? null : (new ManifestExceptionResource($this->exceptionState))->resolve($request),
            'timeline' => $this->timeline($this->resource->timeline),
        ];
    }

    private function statusEvents(Collection $events): array
    {
        return array_map(fn (StatusEventRecord $event): array => [
            'status_event_id' => (string) $event->status_event_id,
            'event_sequence' => $event->event_sequence === null ? null : (int) $event->event_sequence,
            'consignment_id' => (string) $event->consignment_id,
            'consignment_number' => (string) $event->consignment->consignment_number,
            'parcel_id' => $event->parcel_id,
            'parcel_number' => $event->parcel?->parcel_number,
            'previous_status' => (string) $event->previous_status,
            'new_status' => (string) $event->new_status,
            'reason_code' => $event->reason_code,
            'initiator_id' => (string) $event->initiator_id,
            'initiator_name' => (string) ($event->initiator?->display_name ?? ''),
            'node_id' => $event->node_id,
            'node_title' => $event->node?->node_title,
            'created_at' => ManifestTimestamp::format($event->created_at),
        ], $events->all());
    }

    private function timeline(Collection $events): array
    {
        return array_map(fn (AuditEventRecord $event): array => [
            'audit_id' => (string) $event->audit_id,
            'action_key' => (string) $event->action_key,
            'initiator_id' => $event->initiator_id,
            'initiator_name' => (string) ($event->initiator?->display_name ?? ''),
            'correlation_id' => (string) $event->correlation_id,
            'created_at' => ManifestTimestamp::format($event->created_at),
        ], $events->all());
    }
}
