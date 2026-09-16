<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Domain\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class DeliveryTaskReader
{
    public function __construct(private \Modules\Operations\Application\Repositories\DeliveryTaskRepository $tasks)
    {
    }

    public function locked(AuthenticatedPrincipal $actor, string $nodeId, string $id): object
    {
        $row = $this->tasks->lock($actor->hqId, $nodeId, $id);
        if ($row === null) {
            throw new ApiException(ApiErrorCode::ResourceNotFound, 404, 'Resource not found.');
        }
        return $row;
    }

    public function summary(object $row): array
    {
        return [
            'delivery_task_id' => (string) $row->delivery_task_id,
            'consignment_id' => (string) $row->consignment_id,
            'consignment_number' => (string) $row->consignment_number,
            'node_id' => (string) $row->node_id,
            'assigned_driver_id' => $row->assigned_driver_id,
            'manifest_id' => $row->manifest_id,
            'status' => (string) $row->status,
            'attempt_number' => (int) $row->attempt_number,
            'recipient' => [
                'name' => (string) $row->receiver_contact_name,
                'mobile' => (string) $row->receiver_mobile,
                'address' => (string) $row->receiver_address_text,
            ],
            'recipient_name' => $row->recipient_name,
            'proof_type' => $row->proof_type,
            'proof_note' => $row->proof_note,
            'failure_reason_code' => $row->failure_reason_code,
            'failure_reason' => $row->failure_reason,
            'version' => (int) $row->version,
            'delivered_at' => $row->delivered_at,
        ];
    }

    public function resolution(string $consignmentId): ?array
    {
        $row = $this->tasks->resolution($consignmentId);
        if ($row === null) {
            return null;
        }
        return [
            'last_mile_resolution_id' => (string) $row->last_mile_resolution_id,
            'destination_gateway_node_id' => (string) $row->destination_gateway_node_id,
            'last_mile_node_id' => (string) $row->last_mile_node_id,
            'coverage_policy_id' => (string) $row->coverage_policy_id,
            'coverage_policy_version_id' => (string) $row->coverage_policy_version_id,
            'coverage_rule_id' => (string) $row->coverage_rule_id,
            'route_definition_version_id' => $row->route_definition_version_id,
            'resolution_input' => json_decode((string) $row->resolution_input, true, flags: JSON_THROW_ON_ERROR),
            'resolved_at' => $row->resolved_at,
        ];
    }

    public function routeProgress(string $consignmentId): array
    {
        return $this->tasks->routeProgress($consignmentId);
    }
}
