<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Contracts;

use Modules\Consignment\Application\Dto\AcceptedConsignmentQuoteDto;
use Modules\Consignment\Application\Dto\AcceptedQuoteReferenceDto;
use Modules\Consignment\Application\Dto\ConsignmentDraftDto;
use Modules\Consignment\Application\Dto\ConsignmentQuoteBundleDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface PricingServiceInterface
{
    public function calculate(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $purpose,
        ConsignmentDraftDto $input,
        ?string $consignmentId,
        ?int $expectedVersion,
    ): ConsignmentQuoteBundleDto;

    public function accept(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $purpose,
        ConsignmentDraftDto $input,
        AcceptedQuoteReferenceDto $acceptedQuote,
        ?string $consignmentId = null,
        ?int $expectedVersion = null,
    ): AcceptedConsignmentQuoteDto;

    public function consume(string $quoteId): void;
}
