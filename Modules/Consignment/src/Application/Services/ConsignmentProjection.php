<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Services;

final readonly class ConsignmentProjection
{
    public function __construct(
        private \Modules\Consignment\Application\Services\ConsignmentTime $consignmentTime,
        private \Modules\Consignment\Application\Repositories\ConsignmentRepository $consignments,
        private \Modules\Foundation\Application\ScopedAccess $scopedAccess,
        private \Modules\Consignment\Application\Contracts\ConsignmentSettings $settings,
        private \Modules\Consignment\Application\EditPricingImpact $editImpact,
        private \Modules\Consignment\Application\Services\ConsignmentDraft $consignmentDraft,
    )
    {
    }

    public function listItem(array $row): array
    {
        return [
            'consignment_id' => (string) $row['consignment_id'],
            'consignment_number' => (string) $row['consignment_number'],
            'receiver_contact_name' => (string) $row['receiver_contact_name'],
            'receiver_mobile' => (string) $row['receiver_mobile'],
            'receiver_address_text' => (string) $row['receiver_address_text'],
            'pickup_node_id' => $row['pickup_node_id'] ? (string) $row['pickup_node_id'] : null,
            'pickup_node_title' => (string) $row['pickup_node_title'],
            'delivery_node_id' => $row['delivery_node_id'] ? (string) $row['delivery_node_id'] : null,
            'delivery_node_title' => $row['delivery_node_title'] ? (string) $row['delivery_node_title'] : null,
            'pickup_man_id' => $row['pickup_man_id'] ? (string) $row['pickup_man_id'] : null,
            'delivery_man_id' => $row['delivery_man_id'] ? (string) $row['delivery_man_id'] : null,
            'pickup_man_title' => $row['pickup_man_title'] ?? null,
            'delivery_man_title' => $row['delivery_man_title'] ?? null,
            'current_status' => (string) $row['current_status'],
            'aggregate' => $this->aggregateResource($row),
            'parcel_count' => (int) $row['parcel_count'],
            'payable_total_amount' => $row['payable_total_amount'] === null ? null : (int) $row['payable_total_amount'],
            'payable_currency' => $row['payable_currency'] === null ? null : (string) $row['payable_currency'],
            'version' => (int) $row['version'],
            'created_at' => $this->consignmentTime->time($row['created_at']),
            'updated_at' => $this->consignmentTime->time($row['updated_at']),
        ];
    }

    public function detail(array $row, array $context): array
    {
        $hqId = (string) $row['hq_id'];
        $id = (string) $row['consignment_id'];
        $offeringEvidence = $row['service_offering_version_id'] === null ? null : $this->consignments->offeringEvidence($row['service_offering_version_id'], $hqId);
        $parcels = in_array('parcel.view', $context['permissions'], true) && in_array($context['acting_node_id'], $this->scopedAccess->nodes($context, 'parcel.view'), true) ? array_map(fn($parcel): array => [
            'parcel_id' => (string) $parcel->parcel_id,
            'parcel_number' => (string) $parcel->parcel_number,
            'current_status' => (string) $parcel->current_status,
            'content_description' => $parcel->content_description,
            'weight_kg' => $parcel->weight_kg === null ? null : (float) $parcel->weight_kg,
            'width_cm' => $parcel->width_cm === null ? null : (float) $parcel->width_cm,
            'length_cm' => $parcel->length_cm === null ? null : (float) $parcel->length_cm,
            'height_cm' => $parcel->height_cm === null ? null : (float) $parcel->height_cm,
            'current_node_id' => $parcel->current_node_id,
            'current_custody_type' => (string) $parcel->current_custody_type,
            'current_custodian_id' => $parcel->current_custodian_id,
            'active_route_plan_id' => $parcel->active_route_plan_id,
            'active_route_plan_leg_id' => $parcel->active_route_plan_leg_id,
            'version' => (int) $parcel->version,
            'created_at' => $this->consignmentTime->time($parcel->created_at),
        ], $this->consignments->parcelsInNumberOrder($hqId, $id)) : [];
        $pricing = array_map(function ($version) use ($hqId): array {
            $lines = array_map(fn($line): array => [
                'charge_code' => (string) $line->charge_code,
                'title' => (string) $line->title,
                'category' => $line->category,
                'calculation_method' => $line->calculation_method,
                'basis' => $line->basis,
                'quantity' => $line->quantity === null ? null : (float) $line->quantity,
                'unit_rate' => $line->unit_rate === null ? null : (float) $line->unit_rate,
                'amount' => (int) $line->amount,
                'explanation' => $line->explanation ? json_decode((string) $line->explanation, true) : null,
            ], $this->consignments->chargeLines($version->pricing_version_id, $hqId));
            return [
                'pricing_version_id' => (string) $version->pricing_version_id,
                'version_number' => (int) $version->version_number,
                'provider_code' => (string) $version->provider_code,
                'quote_id' => (string) $version->quote_id,
                'quote_version' => (int) $version->quote_version,
                'option_id' => (string) $version->option_id,
                'external_method_code' => (string) $version->external_method_code,
                'method_name' => (string) $version->method_name,
                'external_price_list_code' => $version->external_price_list_code,
                'zone' => $version->zone,
                'currency' => (string) $version->currency,
                'total_amount' => (int) $version->total_amount,
                'min_ins' => $version->min_ins === null ? null : (int) $version->min_ins,
                'delivery_windows' => json_decode((string) $version->delivery_windows, true) ?: [],
                'accepted_at' => $this->consignmentTime->time($version->accepted_at),
                'charge_lines' => $lines,
            ];
        }, $this->consignments->pricingVersions($hqId, $id));
        $statusTimeline = array_map(fn($event): array => [
            'status_event_id' => (string) $event->status_event_id,
            'event_sequence' => $event->event_sequence === null ? null : (int) $event->event_sequence,
            'parcel_id' => $event->parcel_id,
            'previous_status' => $event->previous_status,
            'new_status' => (string) $event->new_status,
            'aggregate_mode' => $event->aggregate_mode,
            'parcel_status_counts' => $event->parcel_status_counts === null ? null : json_decode((string) $event->parcel_status_counts, true),
            'initiator_id' => (string) $event->initiator_id,
            'node_id' => $event->node_id,
            'manifest_id' => $event->manifest_id,
            'correlation_id' => $event->correlation_id,
            'reason_code' => $event->reason_code,
            'note' => $event->note,
            'created_at' => $this->consignmentTime->time($event->created_at),
        ], $this->consignments->statusHistory($hqId, $id));
        $auditTimeline = in_array('audit.view', $context['permissions'], true) && in_array($context['acting_node_id'], $this->scopedAccess->nodes($context, 'audit.view'), true) ? array_map(fn($event): array => [
            'audit_id' => (string) $event->audit_id,
            'action_key' => (string) $event->action_key,
            'initiator_id' => $event->initiator_id,
            'safe_note' => $event->safe_note,
            'created_at' => $this->consignmentTime->time($event->created_at),
        ], $this->consignments->auditHistory($hqId, $id)) : [];
        $custodyTimeline = array_map(fn($event): array => [
            'custody_event_id' => (string) $event->custody_event_id,
            'event_sequence' => $event->event_sequence === null ? null : (int) $event->event_sequence,
            'parcel_id' => (string) $event->parcel_id,
            'from_node_id' => $event->from_node_id,
            'to_node_id' => $event->to_node_id,
            'from_custody_type' => $event->from_custody_type,
            'to_custody_type' => (string) $event->to_custody_type,
            'from_custodian_id' => $event->from_custodian_id,
            'to_custodian_id' => $event->to_custodian_id,
            'command_name' => (string) $event->command_name,
            'manifest_id' => $event->manifest_id,
            'route_plan_id' => $event->route_plan_id,
            'route_plan_leg_id' => $event->route_plan_leg_id,
            'created_at' => $this->consignmentTime->time($event->created_at),
        ], $this->consignments->custodyHistory($hqId, $id));
        $routePlan = $this->consignments->latestRoutePlan($hqId, $id);
        $routeLegs = $routePlan === null ? [] : array_map(fn($leg): array => [
            'route_plan_leg_id' => (string) $leg->route_plan_leg_id,
            'source_route_definition_leg_id' => (string) $leg->source_route_definition_leg_id,
            'source_route_definition_version_leg_id' => (string) $leg->source_route_definition_version_leg_id,
            'leg_order' => (int) $leg->leg_order,
            'status' => (string) $leg->status,
            'origin_node' => [
                'node_id' => (string) $leg->origin_node_id,
                'node_code' => (string) $leg->origin_code,
                'node_title' => (string) $leg->origin_title,
            ],
            'destination_node' => [
                'node_id' => (string) $leg->destination_node_id,
                'node_code' => (string) $leg->destination_code,
                'node_title' => (string) $leg->destination_title,
            ],
            'routed_at' => $leg->routed_at === null ? null : $this->consignmentTime->time($leg->routed_at),
            'received_at' => $leg->received_at === null ? null : $this->consignmentTime->time($leg->received_at),
        ], $this->consignments->routeLegs($routePlan->route_plan_id));
        $location = $this->aggregateLocation($hqId, $id);
        $manifestRows = $this->consignments->relatedManifests($hqId, $id);
        $outcomesByManifest = [];
        if ($manifestRows !== []) {
            foreach ($this->consignments->manifestOutcomes(array_map(fn($manifest) => $manifest->manifest_id, $manifestRows), $hqId, $id) as $outcome) {
                $outcomesByManifest[$outcome->manifest_id][] = (array) $outcome;
            }
        }
        $relatedManifests = array_map(fn($manifest): array => [...(array) $manifest, 'parcel_outcomes' => $outcomesByManifest[$manifest->manifest_id] ?? []], $manifestRows);
        $movementManifests = array_values(array_map(static function (array $manifest): array {
            $succeeded = array_values(array_filter($manifest['parcel_outcomes'], static fn(array $outcome): bool => $outcome['manifest_parcel_status'] === 'SUCCEEDED'))[0] ?? null;
            return [
                ...$manifest,
                'route_plan_id' => $manifest['route_plan_id'] ?? $succeeded['route_plan_id'],
                'route_plan_leg_id' => $manifest['route_plan_leg_id'] ?? $succeeded['route_plan_leg_id'],
                'route_definition_version_id' => $manifest['route_definition_version_id'] ?? $succeeded['route_definition_version_id'],
                'route_definition_version_leg_id' => $manifest['route_definition_version_leg_id'] ?? $succeeded['route_definition_version_leg_id'],
                'event_type' => $manifest['manifest_status'] === 'OS' ? 'DEPARTED' : 'RECEIVED',
                'recorded_at' => $manifest['operation_recorded_at'] ?? $manifest['closed_at'],
            ];
        }, array_filter($relatedManifests, static function (array $manifest): bool {
            $isMovement = in_array($manifest['manifest_status'], ['OS', 'CI'], true) || $manifest['manifest_status'] === 'IR' && $manifest['operational_context_type'] === 'MOVEMENT_RECEPTION';
            $hasSucceededParcel = array_filter($manifest['parcel_outcomes'], static fn(array $outcome): bool => $outcome['manifest_parcel_status'] === 'SUCCEEDED') !== [];
            return $isMovement && $manifest['state'] === 'CLOSED' && $hasSucceededParcel;
        })));
        $routeLegOrder = array_column($routeLegs, 'leg_order', 'route_plan_leg_id');
        usort($movementManifests, static function (array $left, array $right) use ($routeLegOrder): int {
            $leftKey = [
                (int) ($routeLegOrder[$left['route_plan_leg_id']] ?? PHP_INT_MAX),
                $left['event_type'] === 'DEPARTED' ? 0 : 1,
                (string) $left['recorded_at'],
                (string) $left['manifest_id'],
            ];
            $rightKey = [
                (int) ($routeLegOrder[$right['route_plan_leg_id']] ?? PHP_INT_MAX),
                $right['event_type'] === 'DEPARTED' ? 0 : 1,
                (string) $right['recorded_at'],
                (string) $right['manifest_id'],
            ];
            return $leftKey <=> $rightKey;
        });
        $catalogSnapshot = empty($row['catalog_snapshot']) ? [] : json_decode((string) $row['catalog_snapshot'], true);
        $base = $this->listItem($row);
        $editable = in_array('consignment.edit', $context['permissions'], true) && in_array($context['acting_node_id'], $this->scopedAccess->nodes($context, 'consignment.edit'), true) && in_array($row['current_status'], $this->settings->editableStatuses(), true);
        return [
            ...$base,
            'non_pricing_contact_fields' => $this->editImpact->contactFields($row),
            'sender' => $this->consignmentDraft->contactFromRow('sender', $row),
            'receiver' => $this->consignmentDraft->contactFromRow('receiver', $row),
            'service_type_id' => (string) $row['service_type_id'],
            'shipping_method_id' => (string) $row['shipping_method_id'],
            'service_offering_id' => $row['service_offering_id'] ? (string) $row['service_offering_id'] : null,
            'service_offering_version_id' => $row['service_offering_version_id'] ? (string) $row['service_offering_version_id'] : null,
            'service_offering_title' => !empty($catalogSnapshot['labels']) ? $this->historicalLabel(json_encode($catalogSnapshot['labels'])) : ($offeringEvidence === null ? null : $this->historicalLabel($offeringEvidence->offering_labels)),
            'service_type_title' => !empty($catalogSnapshot['service_type_labels']) ? $this->historicalLabel(json_encode($catalogSnapshot['service_type_labels'])) : ($offeringEvidence === null ? null : $this->historicalLabel($offeringEvidence->service_type_labels)),
            'shipping_method_title' => !empty($catalogSnapshot['shipping_method_labels']) ? $this->historicalLabel(json_encode($catalogSnapshot['shipping_method_labels'])) : ($offeringEvidence === null ? null : $this->historicalLabel($offeringEvidence->shipping_method_labels)),
            'catalog_snapshot' => $catalogSnapshot ?: null,
            'selected_service_option_versions' => $row['selected_service_option_versions'] ? json_decode((string) $row['selected_service_option_versions'], true) : [],
            'commitment_schedule_version_id' => $row['commitment_schedule_version_id'] ? (string) $row['commitment_schedule_version_id'] : null,
            'pickup_service_date' => $row['pickup_service_date'],
            'pickup_window_code' => $row['pickup_window_code'],
            'delivery_window_code' => $row['delivery_window_code'],
            'delivery_commitment_resolution' => empty($row['delivery_commitment_resolution']) ? null : json_decode((string) $row['delivery_commitment_resolution'], true),
            'commitment_snapshot' => $row['commitment_snapshot'] ? json_decode((string) $row['commitment_snapshot'], true) : null,
            'commercial_pricing_state' => (string) $row['commercial_pricing_state'],
            'active_pricing_snapshot_id' => $row['active_pricing_snapshot_id'] ? (string) $row['active_pricing_snapshot_id'] : null,
            'pickup_commitment_at' => $row['pickup_commitment_at'] ? $this->consignmentTime->time($row['pickup_commitment_at']) : null,
            'pickup_commitment_start_at' => $row['pickup_commitment_start_at'] ? $this->consignmentTime->time($row['pickup_commitment_start_at']) : null,
            'pickup_commitment_end_at' => $row['pickup_commitment_end_at'] ? $this->consignmentTime->time($row['pickup_commitment_end_at']) : null,
            'delivery_commitment_at' => $row['delivery_commitment_at'] ? $this->consignmentTime->time($row['delivery_commitment_at']) : null,
            'delivery_commitment_start_at' => $row['delivery_commitment_start_at'] ? $this->consignmentTime->time($row['delivery_commitment_start_at']) : null,
            'delivery_commitment_end_at' => $row['delivery_commitment_end_at'] ? $this->consignmentTime->time($row['delivery_commitment_end_at']) : null,
            'weight_kg' => (float) $row['weight_kg'],
            'width_cm' => $row['width_cm'] === null ? null : (float) $row['width_cm'],
            'length_cm' => $row['length_cm'] === null ? null : (float) $row['length_cm'],
            'height_cm' => $row['height_cm'] === null ? null : (float) $row['height_cm'],
            'declared_value_amount' => (int) $row['declared_value_amount'],
            'insurance_enabled' => (bool) $row['insurance_enabled'],
            'insurance_value_amount' => $row['insurance_value_amount'] === null ? null : (int) $row['insurance_value_amount'],
            'cod_enabled' => (bool) $row['cod_enabled'],
            'cod_amount' => $row['cod_amount'] === null ? null : (int) $row['cod_amount'],
            'payer' => (string) $row['payer'],
            'payment_method' => (string) $row['payment_method'],
            'parcels' => $parcels,
            'accepted_pricing_versions' => $pricing,
            'status_timeline' => $statusTimeline,
            'audit_timeline' => $auditTimeline,
            'current_location' => $location,
            'journey' => [
                'route_plan' => $routePlan === null ? null : [
                    'route_plan_id' => (string) $routePlan->route_plan_id,
                    'route_definition_id' => (string) $routePlan->route_definition_id,
                    'route_definition_version_id' => (string) $routePlan->route_definition_version_id,
                    'status' => (string) $routePlan->status,
                    'version' => (int) $routePlan->version,
                ],
                'route_legs' => $routeLegs,
                'movement_manifests' => $movementManifests,
                'custody_timeline' => $custodyTimeline,
            ],
            'related_manifests' => $relatedManifests,
            'permitted_actions' => $editable ? ['EDIT'] : [],
        ];
    }

    public function aggregateLocation(string $hqId, string $consignmentId): array
    {
        $parcels = $this->consignments->parcels($hqId, $consignmentId);
        $nodes = array_values(array_unique(array_map(fn($parcel) => $parcel->current_node_id, $parcels), SORT_REGULAR));
        $custodies = array_values(array_unique(array_map(fn($p): string => (string) $p->current_custody_type . '|' . (string) $p->current_custodian_id, $parcels), SORT_REGULAR));
        if (count($nodes) !== 1 || count($custodies) !== 1) {
            return ['state' => 'MIXED', 'node' => null, 'custody_type' => 'MIXED', 'custodian_id' => null];
        }
        $nodeId = $nodes[0];
        $first = $parcels[0];
        $node = $nodeId === null ? null : $this->consignments->node($hqId, $nodeId);
        return [
            'state' => $nodeId === null ? 'IN_CUSTODY' : 'AT_NODE',
            'node' => $node === null ? null : [
                'node_id' => (string) $node->node_id,
                'node_code' => (string) $node->node_code,
                'node_title' => (string) $node->node_title,
            ],
            'custody_type' => (string) $first->current_custody_type,
            'custodian_id' => $first->current_custodian_id,
        ];
    }

    public function historicalLabel(mixed $labels): ?string
    {
        $decoded = is_string($labels) ? json_decode($labels, true) : (array) $labels;
        foreach (['fa', 'en'] as $locale) {
            $label = trim((string) ($decoded[$locale] ?? ''));
            if ($label !== '') {
                return $label;
            }
        }
        return null;
    }

    public function aggregateResource(array $row): array
    {
        $counts = json_decode((string) ($row['parcel_status_counts'] ?? '{}'), true) ?: [];
        $counts = array_map(static fn($count): int => (int) $count, $counts);
        ksort($counts);
        $status = (string) $row['current_status'];
        return [
            'status' => $status,
            'mode' => (string) ($row['aggregate_mode'] ?? 'FULL'),
            'parcel_counts' => $counts,
            'parcel_total' => array_sum($counts),
            'target_count' => $counts[$status] ?? 0,
        ];
    }
}
