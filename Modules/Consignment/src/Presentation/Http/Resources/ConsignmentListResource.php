<?php

declare(strict_types=1);

namespace Modules\Consignment\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Consignment\Application\Contracts\ConsignmentTimeInterface;
use Modules\Consignment\Domain\Enums\AggregateMode;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;

final class ConsignmentListResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var ConsignmentRecord $row */
        $row = $this->resource;
        $time = app(ConsignmentTimeInterface::class);

        return [
            'consignment_id' => (string) $row->consignment_id,
            'consignment_number' => (string) $row->consignment_number,
            'receiver_contact_name' => (string) $row->receiver_contact_name,
            'receiver_mobile' => (string) $row->receiver_mobile,
            'receiver_address_text' => (string) $row->receiver_address_text,
            'pickup_node_id' => $row->pickup_node_id ? (string) $row->pickup_node_id : null,
            'pickup_node_title' => (string) $row->pickupNode?->node_title,
            'delivery_node_id' => $row->delivery_node_id ? (string) $row->delivery_node_id : null,
            'delivery_node_title' => $row->deliveryNode?->node_title ? (string) $row->deliveryNode?->node_title : null,
            'pickup_man_id' => $row->pickup_man_id ? (string) $row->pickup_man_id : null,
            'delivery_man_id' => $row->delivery_man_id ? (string) $row->delivery_man_id : null,
            'pickup_man_title' => $row->pickupDriver?->display_name ?? null,
            'delivery_man_title' => $row->deliveryDriver?->display_name ?? null,
            'current_status' => (string) $row->current_status,
            'aggregate' => $this->aggregateResource($row),
            'parcel_count' => (int) $row->parcel_count,
            'payable_total_amount' => $row->latestPricing?->total_amount === null ? null : (int) $row->latestPricing?->total_amount,
            'payable_currency' => $row->latestPricing?->currency === null ? null : (string) $row->latestPricing?->currency,
            'version' => (int) $row->version,
            'created_at' => $time->time($row->created_at),
            'updated_at' => $time->time($row->updated_at),
        ];
    }

    private function aggregateResource(ConsignmentRecord $row): array
    {
        $counts = $row->parcel_status_counts ?? [];
        $counts = array_map(static fn ($count): int => (int) $count, $counts);
        ksort($counts);
        $status = (string) $row->current_status;

        return [
            'status' => $status,
            'mode' => (string) ($row->aggregate_mode ?? AggregateMode::Full->value),
            'parcel_counts' => $counts,
            'parcel_total' => array_sum($counts),
            'target_count' => $counts[$status] ?? 0,
        ];
    }
}
