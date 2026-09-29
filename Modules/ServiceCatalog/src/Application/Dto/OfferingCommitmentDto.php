<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

use Carbon\CarbonImmutable;
use Modules\ServiceCatalog\Domain\Enums\CommitmentEvidenceKind;
use Modules\ServiceCatalog\Domain\ValueObjects\CommitmentResolution;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\OfferingCommitmentBindingRecord;

/** Native resolution; policy arrays are immutable JSON evidence, serialized at HTTP/storage boundaries. */
final readonly class OfferingCommitmentDto
{
    public function __construct(
        public CommitmentEvidenceKind $kind,
        public bool $eligible = true,
        public ?string $reasonCode = null,
        public ?CommitmentScheduleVersionRecord $schedule = null,
        public ?OfferingCommitmentBindingRecord $binding = null,
        public CommitmentResolution|LegacyCommitmentPromiseDto|null $pickup = null,
        public CommitmentResolution|LegacyCommitmentPromiseDto|null $delivery = null,
        public ?array $policy = null,
        public ?array $deliveryPolicy = null,
        public ?string $selectedRuleId = null,
        public ?CommitmentDestinationDto $destination = null,
        public ?string $acceptedAt = null,
        public ?string $requestedDeliveryWindowCode = null,
        public ?string $legacyType = null,
        public ?CarbonImmutable $legacyStartsAt = null,
        public ?CarbonImmutable $legacyEndsAt = null,
    ) {}
}
