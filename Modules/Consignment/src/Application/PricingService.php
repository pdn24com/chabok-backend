<?php

declare(strict_types=1);

namespace Modules\Consignment\Application;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class PricingService
{
    public function __construct(
        private \Modules\Consignment\Application\UseCases\CalculateConsignmentQuote\CalculateConsignmentQuoteHandler $calculateConsignmentQuote,
        private \Modules\Consignment\Application\UseCases\AcceptConsignmentQuote\AcceptConsignmentQuoteHandler $acceptConsignmentQuote,
        private \Modules\Consignment\Application\UseCases\ConsumeConsignmentQuote\ConsumeConsignmentQuoteHandler $consumeConsignmentQuote,
    )
    {
    }

    public function calculate(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $purpose,
        array $input,
        ?string $consignmentId,
        ?int $expectedVersion,
    ): array
    {
        return $this->calculateConsignmentQuote->handle(new \Modules\Consignment\Application\UseCases\CalculateConsignmentQuote\CalculateConsignmentQuoteCommand($actor, $nodeId, $purpose, $input, $consignmentId, $expectedVersion))->data;
    }

    public function accept(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $purpose,
        array $input,
        array $acceptedQuote,
        ?string $consignmentId = null,
        ?int $expectedVersion = null,
    ): array
    {
        return $this->acceptConsignmentQuote->handle(new \Modules\Consignment\Application\UseCases\AcceptConsignmentQuote\AcceptConsignmentQuoteCommand($actor, $nodeId, $purpose, $input, $acceptedQuote, $consignmentId, $expectedVersion))->data;
    }

    public function consume(string $quoteId): void
    {
        $this->consumeConsignmentQuote->handle(new \Modules\Consignment\Application\UseCases\ConsumeConsignmentQuote\ConsumeConsignmentQuoteCommand($quoteId));
    }
}
