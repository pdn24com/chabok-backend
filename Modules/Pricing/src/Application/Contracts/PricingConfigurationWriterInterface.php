<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Contracts;

use Modules\Pricing\Application\Dto\PricingRateRuleDraftDto;
use Modules\Pricing\Domain\ValueObjects\CalculationLine;

interface PricingConfigurationWriterInterface
{
    /** @param list<PricingRateRuleDraftDto> $rules */
    public function replaceRules(string $versionId, array $rules): void;

    /** @param list<CalculationLine> $lines */
    public function insertLines(string $parentId, array $lines): void;
}
