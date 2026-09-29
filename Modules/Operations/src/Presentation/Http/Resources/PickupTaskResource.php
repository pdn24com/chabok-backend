<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class PickupTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $row = $this->resource;
        $consignment = $row->consignment;
        $node = $row->node;
        $driver = $row->assignedDriver;

        return [
            'pickup_task_id' => (string) $row->pickup_task_id,
            'consignment_id' => (string) $row->consignment_id,
            'consignment_number' => $consignment === null ? null : (string) $consignment->consignment_number,
            'sender_name' => $consignment === null ? null : (string) $consignment->sender_contact_name,
            'sender_mobile' => $consignment === null ? null : (string) $consignment->sender_mobile,
            'pickup_address' => $consignment === null ? null : (string) $consignment->sender_address_text,
            'pickup_commitment_at' => $consignment?->pickup_commitment_at,
            'pickup_window_code' => $consignment?->pickup_window_code,
            'node' => $node === null ? null : [
                'node_id' => (string) $node->node_id,
                'node_code' => (string) $node->node_code,
                'node_title' => (string) $node->node_title,
            ],
            'assigned_driver' => $driver === null ? null : [
                'driver_id' => (string) $driver->driver_id,
                'driver_code' => (string) $driver->driver_code,
                'display_name' => (string) $driver->display_name,
            ],
            'status' => $row->status->value,
            'failure_reason_code' => $row->failure_reason_code,
            'failure_reason' => $row->failure_reason,
            'version' => (int) $row->version,
            'assigned_at' => $row->assigned_at,
            'started_at' => $row->started_at,
            'completed_at' => $row->completed_at,
            'failed_at' => $row->failed_at,
            'created_at' => $row->created_at,
        ];
    }
}
