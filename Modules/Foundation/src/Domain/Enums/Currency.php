<?php

declare(strict_types=1);

namespace Modules\Foundation\Domain\Enums;

/** ISO 4217 codes the platform prices in. */
enum Currency: string
{
    case Irr = 'IRR';
}
