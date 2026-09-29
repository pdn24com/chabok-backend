<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\ValueObjects;

final readonly class FreightMatrixBand
{
    /** @param list<FreightMatrixCell> $cells */
    public function __construct(
        public string $id,
        public int|float|string $from,
        public int|float|string|null $to,
        public array $cells,
        public int|float|string|null $stepKg = null,
    ) {}
}
