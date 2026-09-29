<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Domain\ValueObjects;

use Carbon\CarbonImmutable;
use Modules\ServiceCatalog\Domain\Enums\CommitmentAnchor;
use Modules\ServiceCatalog\Domain\Enums\CommitmentWindowType;

final readonly class CommitmentContext
{
    public function __construct(
        public CarbonImmutable $acceptedAt,
        public ?CarbonImmutable $pickupCompletedAt = null,
        public ?CarbonImmutable $pickupStartsAt = null,
        public ?CarbonImmutable $pickupEndsAt = null,
        public ?string $pickupServiceDate = null,
        public ?string $pickupWindowCode = null,
        public ?string $deliveryWindowCode = null,
    ) {}

    public function anchor(CommitmentAnchor $anchor): ?CarbonImmutable
    {
        return match ($anchor) {
            CommitmentAnchor::ConsignmentCreated => $this->acceptedAt,
            CommitmentAnchor::PickupCompleted => $this->pickupCompletedAt,
            CommitmentAnchor::PickupStart => $this->pickupStartsAt,
            CommitmentAnchor::PickupEnd => $this->pickupEndsAt,
        };
    }

    public function selectedWindow(CommitmentWindowType $type): ?string
    {
        return $type === CommitmentWindowType::Pickup ? $this->pickupWindowCode : $this->deliveryWindowCode;
    }

    public function withPickup(CommitmentResolution $pickup): self
    {
        return new self($this->acceptedAt, $this->pickupCompletedAt,
            $pickup->selected?->startsAt ?? $pickup->computedAt,
            $pickup->selected?->endsAt ?? $pickup->computedAt,
            $this->pickupServiceDate, $this->pickupWindowCode, $this->deliveryWindowCode);
    }
}
