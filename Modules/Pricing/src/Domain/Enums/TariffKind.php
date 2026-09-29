<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Enums;

enum TariffKind: string
{
    case Freight = 'FREIGHT';
    case Service = 'SERVICE';
}
