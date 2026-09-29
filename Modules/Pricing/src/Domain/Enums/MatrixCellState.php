<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Enums;

enum MatrixCellState: string
{
    case EMPTY = 'EMPTY';
    case RATE = 'RATE';
    case UNCOVERED = 'UNCOVERED';
}
