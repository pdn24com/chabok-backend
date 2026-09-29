<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Mappers;

use Modules\Foundation\Application\Mappers\CoverageAddressInput;
use Modules\ServiceCatalog\Domain\ValueObjects\OfferingSelectionContext;

final class OfferingSelectionInput
{
    public static function fromArray(array $input): OfferingSelectionContext
    {
        return new OfferingSelectionContext(
            sender: CoverageAddressInput::fromArray((array) ($input['sender'] ?? [])),
            receiver: CoverageAddressInput::fromArray((array) ($input['receiver'] ?? [])),
            asOfTimestamp: $input['as_of_timestamp'] ?? null,
            channel: $input['channel'] ?? null,
            serviceOfferingVersionId: $input['service_offering_version_id'] ?? null,
            acceptanceAt: $input['acceptance_at'] ?? null,
            pickupCompletedAt: $input['pickup_completed_at'] ?? null,
            pickupStartsAt: $input['pickup_starts_at'] ?? null,
            pickupEndsAt: $input['pickup_ends_at'] ?? null,
            pickupServiceDate: $input['pickup_service_date'] ?? null,
            pickupWindowCode: $input['pickup_window_code'] ?? null,
            deliveryWindowCode: $input['delivery_window_code'] ?? null,
            selectedOptionVersionIds: array_map('strval', array_values($input['selected_option_version_ids'] ?? [])),
            scheduleNodeIds: array_values($input['schedule_node_ids'] ?? []),
            destination: isset($input['destination']) ? CoverageAddressInput::fromArray($input['destination']) : null,
            receiverProvided: isset($input['receiver']),
            factKeys: array_keys($input),
            facts: array_diff_key($input, array_flip(['sender', 'receiver', 'destination', 'selected_option_version_ids', 'schedule_node_ids', 'as_of_timestamp', 'channel', 'service_offering_version_id', 'acceptance_at', 'pickup_completed_at', 'pickup_starts_at', 'pickup_ends_at', 'pickup_service_date', 'pickup_window_code', 'delivery_window_code'])),
        );
    }
}
