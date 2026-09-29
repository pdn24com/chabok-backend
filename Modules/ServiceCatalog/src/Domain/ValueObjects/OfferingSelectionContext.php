<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\ValueObjects;

use Modules\Foundation\Domain\ValueObjects\CoverageAddress;

/** Typed runtime selection; facts contains only additional, user-configured eligibility facts. */
final class OfferingSelectionContext
{
    /** @param list<string> $selectedOptionVersionIds @param list<string> $scheduleNodeIds @param array<string, mixed> $facts */
    public function __construct(
        public CoverageAddress $sender,
        public CoverageAddress $receiver,
        public ?string $asOfTimestamp = null,
        public ?string $channel = null,
        public ?string $serviceOfferingVersionId = null,
        public ?string $acceptanceAt = null,
        public ?string $pickupCompletedAt = null,
        public ?string $pickupStartsAt = null,
        public ?string $pickupEndsAt = null,
        public ?string $pickupServiceDate = null,
        public ?string $pickupWindowCode = null,
        public ?string $deliveryWindowCode = null,
        public array $selectedOptionVersionIds = [],
        public array $scheduleNodeIds = [],
        public array $facts = [],
        /** @var list<string> Original fact key order for wildcard/first/last expressions. */
        public array $factKeys = [],
        public ?CoverageAddress $destination = null,
        public bool $receiverProvided = true,
    ) {}

    public function factValue(string $key): mixed
    {
        return match ($key) {
            'sender' => $this->sender,
            'receiver' => $this->receiver,
            'destination' => $this->destination,
            'selected_option_version_ids' => $this->selectedOptionVersionIds,
            'schedule_node_ids' => $this->scheduleNodeIds,
            'as_of_timestamp' => $this->asOfTimestamp,
            'channel' => $this->channel,
            'service_offering_version_id' => $this->serviceOfferingVersionId,
            'acceptance_at' => $this->acceptanceAt,
            'pickup_completed_at' => $this->pickupCompletedAt,
            'pickup_starts_at' => $this->pickupStartsAt,
            'pickup_ends_at' => $this->pickupEndsAt,
            'pickup_service_date' => $this->pickupServiceDate,
            'pickup_window_code' => $this->pickupWindowCode,
            'delivery_window_code' => $this->deliveryWindowCode,
            default => $this->facts[$key] ?? null,
        };
    }

    public function commitmentDestination(): CoverageAddress
    {
        return $this->receiverProvided ? $this->receiver : ($this->destination ?? $this->receiver);
    }

    /** Dynamic rule expressions are the dictionary boundary; application flow uses typed properties. */
    public function expressionFacts(): array
    {
        $values = [];
        foreach ($this->factKeys as $key) {
            $values[$key] = $this->factValue($key);
        }

        return $values;
    }
}
