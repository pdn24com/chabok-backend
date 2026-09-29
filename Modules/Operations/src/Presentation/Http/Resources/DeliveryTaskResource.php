<?php

declare(strict_types=1);

namespace Modules\Operations\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class DeliveryTaskResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $row = $this->resource;

        return [
            'delivery_task_id' => (string) $row->delivery_task_id,
            'consignment_id' => (string) $row->consignment_id,
            'consignment_number' => (string) $row->consignment->consignment_number,
            'node_id' => (string) $row->node_id,
            'assigned_driver_id' => $row->assigned_driver_id,
            'manifest_id' => $row->manifest_id,
            'status' => $row->status->value,
            'attempt_number' => (int) $row->attempt_number,
            'recipient' => [
                'name' => (string) $row->consignment->receiver_contact_name,
                'mobile' => (string) $row->consignment->receiver_mobile,
                'address' => (string) $row->consignment->receiver_address_text,
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
}
