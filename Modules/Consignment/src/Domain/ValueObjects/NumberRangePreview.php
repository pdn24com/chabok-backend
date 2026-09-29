<?php

declare(strict_types=1);

namespace Modules\Consignment\Domain\ValueObjects;

final readonly class NumberRangePreview
{
    /** @param list<string> $sampleFirstValues @param list<string> $sampleFinalValues */
    public function __construct(
        public string $numericPrefix,
        public int $totalLength,
        public int $serialWidth,
        public string $serialStart,
        public string $serialEnd,
        public string $firstNumber,
        public string $lastNumber,
        public string $totalCapacity,
        public array $sampleFirstValues,
        public array $sampleFinalValues,
    ) {}
}
