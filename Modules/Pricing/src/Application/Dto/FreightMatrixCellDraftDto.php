<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Dto;

/** One editable matrix cell; the raw state is kept so an unknown value still reports its own field path. */
final class FreightMatrixCellDraftDto
{
    public function __construct(
        public string $id,
        public string $zoneId,
        public string $state,
        public int|float|string|null $amount = null,
    ) {}
}
