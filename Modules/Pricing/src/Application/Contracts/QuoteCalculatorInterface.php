<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Pricing\Application\Dto\PricingSimulationDto;
use Modules\Pricing\Application\Dto\QuoteInputDto;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingQuoteRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffVersionRecord;

interface QuoteCalculatorInterface
{
    public function calculate(AuthenticatedPrincipal $actor, QuoteInputDto $input, string $idempotencyKey, ?TariffVersionRecord $draft = null): PricingQuoteRecord|PricingSimulationDto;
}
