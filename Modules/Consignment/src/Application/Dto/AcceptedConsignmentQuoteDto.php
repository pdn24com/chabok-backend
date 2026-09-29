<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Dto;

final readonly class AcceptedConsignmentQuoteDto
{
    public function __construct(public ConsignmentPricingOptionDto $option, public string $quoteId, public int $quoteVersion,
        public string $inputFingerprint, public string $providerCalculatedAt) {}
}
