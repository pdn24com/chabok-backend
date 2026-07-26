<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Contracts;

interface PricingQuoteProvider
{
    /**
     * @param array<string, mixed> $normalizedInput
     * @return list<array<string, mixed>>
     */
    public function calculate(array $normalizedInput): array;
}
