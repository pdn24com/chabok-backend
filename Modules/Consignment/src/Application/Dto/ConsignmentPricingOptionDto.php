<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Dto;

use Modules\Foundation\Domain\Enums\Currency;

final class ConsignmentPricingOptionDto
{
    public function __construct(
        public bool $available = false,
        public string $externalMethodCode = '',
        public string $methodName = '',
        public ?string $icon = null,
        public ?string $externalPriceListCode = null,
        public ?string $zone = null,
        public string $currency = Currency::Irr->value,
        public ?int $totalAmount = null,
        public ?int $minIns = null,
        public ?float $billableWeightKg = null,
        public ?string $unavailableReason = null,
        public ?string $providerCode = null,
        public ?string $internalQuoteId = null,
        public ?string $serviceOfferingId = null,
        public ?string $serviceOfferingVersionId = null,
        public ?string $serviceTypeId = null,
        public ?string $shippingMethodId = null,
        public ?string $resultFingerprint = null,
        public ?array $catalogSnapshot = null,
        public ?array $commitment = null,
        public array $selectedOptionVersionIds = [],
        public array $warnings = [],
        public ?string $optionId = null,
        public ?string $resolvedInputFingerprint = null,
        public array $chargeLines = [],
        public array $deliveryWindows = [],
        public array $presentFields = [],
    ) {}
}
