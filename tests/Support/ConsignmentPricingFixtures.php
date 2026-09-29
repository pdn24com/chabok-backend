<?php

declare(strict_types=1);

namespace Tests\Support;

use Modules\Consignment\Application\Contracts\PricingServiceInterface;
use Modules\Consignment\Application\Mappers\ConsignmentInputMapper;
use Modules\Consignment\Presentation\Http\Resources\ConsignmentQuoteResource;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

/** Exercise the same typed input and HTTP resource boundary as the controller. */
final readonly class ConsignmentPricingFixtures
{
    public function __construct(private PricingServiceInterface $pricingService) {}

    public function calculate(AuthenticatedPrincipal $actor, string $nodeId, string $purpose, array $input, ?string $consignmentId, ?int $expectedVersion): array
    {
        return (new ConsignmentQuoteResource($this->pricingService->calculate($actor, $nodeId, $purpose, ConsignmentInputMapper::draft($input), $consignmentId, $expectedVersion)))->resolve();
    }

    public function consume(string $quoteId): void
    {
        $this->pricingService->consume($quoteId);
    }
}
