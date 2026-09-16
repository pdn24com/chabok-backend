<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Services;

use Modules\Pricing\Application\Contracts\ConsignmentQuoteAcceptance;
use Modules\Pricing\Application\UseCases\AcceptConsignmentPricingQuote\AcceptConsignmentPricingQuoteCommand;
use Modules\Pricing\Application\UseCases\AcceptConsignmentPricingQuote\AcceptConsignmentPricingQuoteHandler;

final readonly class ConsignmentQuoteAcceptor implements ConsignmentQuoteAcceptance
{
    public function __construct(private AcceptConsignmentPricingQuoteHandler $handler)
    {
    }

    public function acceptForConsignment(string $hqId, string $consignmentId, string $actorId, int $version, array $accepted): string
    {
        return $this->handler->handle(new AcceptConsignmentPricingQuoteCommand($hqId, $consignmentId, $actorId, $version, $accepted))->snapshotId;
    }
}
