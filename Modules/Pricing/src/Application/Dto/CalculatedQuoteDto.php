<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

use Carbon\CarbonImmutable;
use Modules\Pricing\Domain\ValueObjects\CalculationResult;

final readonly class CalculatedQuoteDto
{
    /** @param list<string> $warnings */
    public function __construct(
        public QuoteInputDto $input,
        public QuoteResolutionDto $resolution,
        public CalculationResult $calculation,
        public string $inputFingerprint,
        public CarbonImmutable $calculatedAt,
        public array $warnings,
    ) {}
}
