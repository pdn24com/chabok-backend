<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Services;

use Modules\Consignment\Application\Contracts\PricingServiceInterface;
use Modules\Consignment\Application\Dto\AcceptedConsignmentQuoteDto;
use Modules\Consignment\Application\Dto\AcceptedQuoteReferenceDto;
use Modules\Consignment\Application\Dto\ConsignmentDraftDto;
use Modules\Consignment\Application\Dto\ConsignmentQuoteBundleDto;
use Modules\Consignment\Application\UseCases\AcceptConsignmentQuote\AcceptConsignmentQuoteCommand;
use Modules\Consignment\Application\UseCases\AcceptConsignmentQuote\AcceptConsignmentQuoteHandler;
use Modules\Consignment\Application\UseCases\CalculateConsignmentQuote\CalculateConsignmentQuoteCommand;
use Modules\Consignment\Application\UseCases\CalculateConsignmentQuote\CalculateConsignmentQuoteHandler;
use Modules\Consignment\Application\UseCases\ConsumeConsignmentQuote\ConsumeConsignmentQuoteCommand;
use Modules\Consignment\Application\UseCases\ConsumeConsignmentQuote\ConsumeConsignmentQuoteHandler;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class PricingService implements PricingServiceInterface
{
    public function __construct(
        private CalculateConsignmentQuoteHandler $calculateConsignmentQuoteHandler,
        private AcceptConsignmentQuoteHandler $acceptConsignmentQuoteHandler,
        private ConsumeConsignmentQuoteHandler $consumeConsignmentQuoteHandler,
    ) {}

    public function calculate(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $purpose,
        ConsignmentDraftDto $input,
        ?string $consignmentId,
        ?int $expectedVersion,
    ): ConsignmentQuoteBundleDto {
        return $this->calculateConsignmentQuoteHandler->handle(new CalculateConsignmentQuoteCommand($actor, $nodeId, $purpose, $input, $consignmentId, $expectedVersion));
    }

    public function accept(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $purpose,
        ConsignmentDraftDto $input,
        AcceptedQuoteReferenceDto $acceptedQuote,
        ?string $consignmentId = null,
        ?int $expectedVersion = null,
    ): AcceptedConsignmentQuoteDto {
        return $this->acceptConsignmentQuoteHandler->handle(new AcceptConsignmentQuoteCommand($actor, $nodeId, $purpose, $input, $acceptedQuote, $consignmentId, $expectedVersion));
    }

    public function consume(string $quoteId): void
    {
        $this->consumeConsignmentQuoteHandler->handle(new ConsumeConsignmentQuoteCommand($quoteId));
    }
}
