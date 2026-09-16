<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Services;

final readonly class ManifestWorkflowProjection
{
    public function __construct(
        private \Modules\Operations\Application\Contracts\ManifestExceptionAccess $exceptionState,
        private \Modules\Manifest\Application\Repositories\ManifestWorkflowRepository $workflow,
        private \Modules\Consignment\Application\Contracts\ManifestConsignmentAccess $consignmentState,
    )
    {
    }

    public function caseResource(object $c): array
    {
        $history = array_map(fn(object $h): array => [
            'action' => (string) $h->action,
            'actor' => ['user_id' => (string) $h->actor_id, 'display_name' => (string) $h->display_name],
            'safe_reason' => $h->safe_note,
            'manifest_version' => (int) ($h->manifest_version ?? 1),
            'exception_version' => (int) ($h->exception_version ?? 1),
            'occurred_at' => (string) $h->created_at,
        ], $this->exceptionState->exceptionHistory($c->exception_case_id));
        $submitter = $this->workflow->userDisplayName($c->submitted_by);
        $reviewer = $c->reviewed_by ? $this->workflow->userDisplayName($c->reviewed_by) : null;
        return [
            'exception_case_id' => (string) $c->exception_case_id,
            'manifest_id' => (string) $c->manifest_id,
            'exception_type' => (string) $c->exception_type,
            'status' => (string) $c->case_status,
            'version' => (int) $c->version,
            'submission_sequence' => (int) $c->submission_sequence,
            'reason_code' => (string) $c->reason_code,
            'description' => (string) $c->description,
            'submitted_by' => ['user_id' => (string) $c->submitted_by, 'display_name' => (string) $submitter],
            'submitted_at' => (string) $c->created_at,
            'reviewed_by' => $c->reviewed_by ? ['user_id' => (string) $c->reviewed_by, 'display_name' => (string) $reviewer] : null,
            'reviewed_at' => $c->reviewed_at,
            'decision_reason' => $c->decision_note,
            'history' => $history,
        ];
    }

    public function custodyEvents(string $hq, string $manifest): array
    {
        return array_map(fn(object $e): array => [
            'custody_event_id' => (string) $e->custody_event_id,
            'consignment_id' => (string) $e->consignment_id,
            'parcel_id' => (string) $e->parcel_id,
            'from_node_id' => $e->from_node_id,
            'to_node_id' => $e->to_node_id,
            'from_custody_type' => $e->from_custody_type,
            'to_custody_type' => (string) $e->to_custody_type,
            'initiator_id' => (string) $e->initiator_id,
            'created_at' => (string) $e->created_at,
        ], $this->consignmentState->custodyEvents($hq, $manifest));
    }

    public function movementEvidence(object $m): ?array
    {
        if (!in_array((string) $m->manifest_status, ['ROU', 'OF', 'OS', 'CI', 'IR'], true)) {
            return null;
        }
        return [
            'target_status' => (string) $m->manifest_status,
            'context_key' => (string) $m->context_key,
            'operational_context_type' => (string) $m->operational_context_type,
            'origin_node_id' => $m->origin_node_id,
            'destination_node_id' => $m->destination_node_id,
            'route_plan_id' => $m->route_plan_id,
            'route_plan_leg_id' => $m->route_plan_leg_id,
            'route_definition_version_id' => $m->route_definition_version_id,
            'route_definition_version_leg_id' => $m->route_definition_version_leg_id,
            'driver_id' => $m->assigned_driver_id,
            'vehicle_id' => $m->assigned_vehicle_id,
            'source_manifest_id' => $m->source_manifest_id,
            'recorded_at' => $m->operation_recorded_at ?? $m->closed_at,
        ];
    }
}
