<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

use Modules\Manifest\Domain\ManifestEligibilityReason;
use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class ManifestReader
{
    public function __construct(
        private \Modules\Manifest\Application\ManifestOperationalContext $operationalContext,
        private \Modules\Manifest\Application\ManifestEligibilityEvaluator $eligibility,
        private \Modules\Manifest\Application\Repositories\ManifestRepository $manifests,
        private \Modules\Manifest\Application\ManifestOrchestrationService $orchestration,
    )
    {
    }

    public function listItem(object|array $row): array
    {
        $r = (array) $row;
        $counts = $r['_list_counts'] ?? $this->counts((string) $r['manifest_id']);
        $context = $r['_list_context'] ?? $this->operationalContext->summary((object) $r);
        return [
            'manifest_id' => (string) $r['manifest_id'],
            'manifest_number' => (string) $r['manifest_number'],
            'node_id' => (string) $r['node_id'],
            'manifest_status' => (string) $r['manifest_status'],
            'state' => (string) $r['state'],
            'manifest_type' => $r['manifest_type'] === null ? $this->manifestType((string) $r['manifest_status']) : (string) $r['manifest_type'],
            'operational_context_type' => (string) $r['operational_context_type'],
            'context_key' => (string) $r['context_key'],
            'origin_node_id' => $r['origin_node_id'],
            'destination_node_id' => $r['destination_node_id'],
            'route_plan_id' => $r['route_plan_id'],
            'route_plan_leg_id' => $r['route_plan_leg_id'],
            'assigned_driver_id' => $r['assigned_driver_id'],
            'assigned_vehicle_id' => $r['assigned_vehicle_id'],
            'version' => (int) $r['version'],
            'total_count' => array_sum($counts),
            'succeeded_count' => $counts['succeeded'],
            'failed_count' => $counts['failed'],
            'created_at' => $this->time($r['created_at']),
            'updated_at' => $this->time($r['updated_at']),
            'issuing_node' => $context['issuing_node'],
            'target_node' => $context['target_node'],
            'context' => $context,
        ];
    }

    public function detail(AuthenticatedPrincipal $actor, string $nodeId, string $id, array $context): array
    {
        $m = $this->visible($actor, $nodeId, $id);
        $base = $this->listItem($m);
        $parcels = array_map(fn($r): array => [
            'manifest_parcel_id' => (string) $r->manifest_parcel_id,
            'parcel_id' => (string) $r->parcel_id,
            'parcel_number' => (string) $r->parcel_number,
            'consignment_id' => (string) $r->consignment_id,
            'consignment_number' => (string) $r->consignment_number,
            'receiver_contact_name' => (string) $r->receiver_contact_name,
            'current_status' => (string) $r->current_status,
            'manifest_parcel_status' => (string) $r->manifest_parcel_status,
            'failure_code' => $r->failure_code,
            'failure_reason' => $r->failure_reason,
            'eligibility' => $r->failure_code !== null ? ManifestEligibilityReason::metadata((string) $r->failure_code) : ($r->manifest_parcel_status === 'SUCCEEDED' ? ManifestEligibilityReason::metadata(ManifestEligibilityReason::Eligible) : $this->eligibility->evaluate($r, $m, $nodeId)),
            'input_source' => (string) $r->input_source,
            'input_value' => (string) $r->input_value,
            'processed_at' => $r->processed_at ? $this->time($r->processed_at) : null,
            'created_at' => $this->time($r->created_at),
        ], $this->manifests->parcelDetails($id));
        $actions = [];
        if (in_array($m->state, ['DRAFT', 'OPEN'], true) && in_array('manifest.edit', $context['permissions'], true)) {
            $actions = ['EDIT', 'INSERT', 'VALIDATE'];
        }
        if ($m->state === 'OPEN' && in_array('manifest.approve', $context['permissions'], true)) {
            $actions[] = 'CONFIRM';
        }
        $exceptionState = in_array((string) $m->manifest_status, ['NPU', 'NOK'], true) ? $this->orchestration->exceptionState($actor, $nodeId, $id) : null;
        return [
            ...$base,
            'created_by' => (string) $m->created_by,
            'approved_by' => $m->approved_by,
            'closed_at' => $m->closed_at ? $this->time($m->closed_at) : null,
            'parcels' => $parcels,
            'bucket_counts' => $this->counts($id),
            'permitted_actions' => $actions,
            'status_events' => $this->statusEvents((string) $m->hq_id, $id),
            'custody_events' => $this->orchestration->custodyEvents((string) $m->hq_id, $id),
            'movement_evidence' => $this->orchestration->movementEvidence($m),
            'exception_state' => $exceptionState,
            'timeline' => $this->timeline((string) $m->hq_id, $id),
        ];
    }

    public function statusEvents(string $hqId, string $manifestId): array
    {
        return array_map(fn(object $event): array => [
            'status_event_id' => (string) $event->status_event_id,
            'event_sequence' => $event->event_sequence === null ? null : (int) $event->event_sequence,
            'consignment_id' => (string) $event->consignment_id,
            'consignment_number' => (string) $event->consignment_number,
            'parcel_id' => $event->parcel_id,
            'parcel_number' => $event->parcel_number,
            'previous_status' => (string) $event->previous_status,
            'new_status' => (string) $event->new_status,
            'reason_code' => $event->reason_code,
            'initiator_id' => (string) $event->initiator_id,
            'initiator_name' => (string) ($event->initiator_name ?? ''),
            'node_id' => $event->node_id,
            'node_title' => $event->node_title,
            'created_at' => $this->time($event->created_at),
        ], $this->manifests->statusEvents($hqId, $manifestId));
    }

    public function timeline(string $hqId, string $manifestId): array
    {
        return array_map(fn(object $event): array => [
            'audit_id' => (string) $event->audit_id,
            'action_key' => (string) $event->action_key,
            'initiator_id' => $event->initiator_id,
            'initiator_name' => (string) ($event->initiator_name ?? ''),
            'correlation_id' => (string) $event->correlation_id,
            'created_at' => $this->time($event->created_at),
        ], $this->manifests->auditEvents($hqId, $manifestId));
    }

    public function visible(AuthenticatedPrincipal $actor, string $nodeId, string $id): object
    {
        $row = $this->manifests->find($actor->hqId, $nodeId, $id);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        return $row;
    }

    public function locked(AuthenticatedPrincipal $actor, string $nodeId, string $id): object
    {
        $row = $this->manifests->lock($actor->hqId, $nodeId, $id);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        return $row;
    }

    public function counts(string $id): array
    {
        $raw = $this->manifests->counts($id);
        return [
            'pending' => (int) ($raw['PENDING'] ?? 0),
            'validated' => (int) ($raw['VALIDATED'] ?? 0),
            'succeeded' => (int) ($raw['SUCCEEDED'] ?? 0),
            'failed' => (int) ($raw['FAILED'] ?? 0),
            'skipped' => (int) ($raw['SKIPPED'] ?? 0),
        ];
    }

    public function time(mixed $value): string
    {
        return \Carbon\CarbonImmutable::parse((string) $value, 'UTC')->utc()->toISOString();
    }

    public function manifestType(string $target): string
    {
        return match ($target) {
            'PD' => 'PICKUP_ASSIGNMENT',
            'PU' => 'PICKUP_COMPLETION',
            'NPU' => 'PICKUP_EXCEPTION',
            'IR' => 'INBOUND_RECEPTION',
            'ROU' => 'ROUTE_REGISTRATION',
            'OF' => 'OUTBOUND_TRANSFER',
            'OS' => 'LINEHAUL_DEPARTURE',
            'CI' => 'TRANSIT_UNLOAD',
            'OD' => 'DELIVERY_ASSIGNMENT',
            'OK' => 'DELIVERY_COMPLETION',
            'NOK' => 'DELIVERY_EXCEPTION',
        };
    }
}
