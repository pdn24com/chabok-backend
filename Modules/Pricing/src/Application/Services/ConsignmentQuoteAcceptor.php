<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Modules\Pricing\Application\Contracts\ConsignmentQuoteAcceptorInterface;
use Modules\Pricing\Application\UseCases\AcceptConsignmentPricingQuote\AcceptConsignmentPricingQuoteCommand;
use Modules\Pricing\Application\UseCases\AcceptConsignmentPricingQuote\AcceptConsignmentPricingQuoteHandler;

final readonly class ConsignmentQuoteAcceptor implements ConsignmentQuoteAcceptorInterface
{
    public function __construct(private AcceptConsignmentPricingQuoteHandler $acceptConsignmentPricingQuoteHandler) {}

    public function acceptForConsignment(
        string $hqId,
        string $consignmentId,
        string $actorId,
        int $version,
        string $internalQuoteId,
    ): string {
        return $this->acceptConsignmentPricingQuoteHandler->handle(new AcceptConsignmentPricingQuoteCommand($hqId, $consignmentId, $actorId, $version, $internalQuoteId));
    }
}
