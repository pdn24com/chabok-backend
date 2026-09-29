<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

/**
 * Typed request values. Numeric strings retain their wire representation for stable fingerprints.
 * presentFields preserves omitted versus null; extensions are opaque transport metadata only.
 */
final class QuoteInputDto
{
    /** @param list<string> $presentFields @param array<string, mixed> $extensions @param list<string> $selectedOptionVersionIds @param list<QuoteParcelDto> $parcels */
    public function __construct(
        public QuoteContactDto $sender,
        public QuoteContactDto $receiver,
        public string $purpose = 'SALES',
        public string $channel = 'BRANCH',
        public ?string $asOfTimestamp = null,
        public ?string $acceptanceAt = null,
        public string $serviceOfferingId = '',
        public ?string $serviceOfferingVersionId = null,
        public array $selectedOptionVersionIds = [],
        public ?string $pickupServiceDate = null,
        public ?string $pickupWindowCode = null,
        public ?string $deliveryWindowCode = null,
        public int|float|string|null $weightKg = null,
        public int|float|string|null $lengthCm = null,
        public int|float|string|null $widthCm = null,
        public int|float|string|null $heightCm = null,
        public int|string $declaredValueAmount = 0,
        public bool|int|string $insuranceEnabled = false,
        public bool|int|string $codEnabled = false,
        public int|string|null $codAmount = null,
        public array $parcels = [],
        public array $presentFields = [],
        public array $extensions = [],
    ) {}
}
