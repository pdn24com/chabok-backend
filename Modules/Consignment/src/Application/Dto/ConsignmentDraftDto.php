<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Dto;

/** Explicit commercial data. Numeric wire values and field presence are retained for quote matching.
 * @param  list<string>  $presentFields
 */
final class ConsignmentDraftDto
{
    /**
     * @param  list<string>  $selectedOptionVersionIds
     * @param  list<ConsignmentParcelDto>  $parcels
     * @param  array<string, mixed>|null  $commitmentSnapshot  Frozen quote evidence.
     * @param  array<string, mixed>|null  $deliveryCommitmentResolution  Frozen operational evidence.
     * @param  list<string>  $presentFields
     */
    public function __construct(
        public ConsignmentContactDto $sender,
        public ConsignmentContactDto $receiver,
        public ?string $deliveryNodeId = null,
        public ?string $serviceTypeId = null,
        public ?string $shippingMethodId = null,
        public ?string $serviceOfferingId = null,
        public ?string $serviceOfferingVersionId = null,
        public ?string $commitmentScheduleVersionId = null,
        public ?string $pickupServiceDate = null,
        public ?string $pickupWindowCode = null,
        public ?string $deliveryWindowCode = null,
        public ?string $pickupCommitmentAt = null,
        public ?string $pickupCommitmentStartAt = null,
        public ?string $pickupCommitmentEndAt = null,
        public ?string $deliveryCommitmentAt = null,
        public ?string $deliveryCommitmentStartAt = null,
        public ?string $deliveryCommitmentEndAt = null,
        public array $selectedOptionVersionIds = [],
        public int|float|string|null $weightKg = null,
        public int|float|string|null $widthCm = null,
        public int|float|string|null $lengthCm = null,
        public int|float|string|null $heightCm = null,
        public int|string|null $declaredValueAmount = null,
        public int|string|null $insuranceValueAmount = null,
        public int|string|null $codAmount = null,
        public bool|int|string $insuranceEnabled = false,
        public bool|int|string $codEnabled = false,
        public ?string $payer = null,
        public ?string $paymentMethod = null,
        public ?array $commitmentSnapshot = null,
        public ?array $deliveryCommitmentResolution = null,
        public array $parcels = [],
        public array $presentFields = [],
    ) {}
}
