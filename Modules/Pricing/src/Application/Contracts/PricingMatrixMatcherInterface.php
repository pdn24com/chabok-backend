<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Contracts;

use Modules\Pricing\Application\Dto\TariffMatrixSelectionDto;

interface PricingMatrixMatcherInterface
{
    /** @param list<string> $selectedOptionVersionIds */
    public function matrixCell(TariffMatrixSelectionDto $tariff, string $offeringId, array $selectedOptionVersionIds, string $originCode, string $basisCode, float $weight): ?string;
}
