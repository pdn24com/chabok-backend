<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\ValueObjects;

use Modules\Pricing\Domain\Enums\MatrixCellState;

final readonly class FreightMatrixCell
{
    public function __construct(
        public string $id,
        public string $zoneId,
        public ?MatrixCellState $state,
        public int|float|string|null $amount,
    ) {}
}
