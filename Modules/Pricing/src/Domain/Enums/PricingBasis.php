<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Enums;

enum PricingBasis: string
{
    case FLAT = 'FLAT';
    case SHIPMENT = 'SHIPMENT';
    case ACTUAL_WEIGHT = 'ACTUAL_WEIGHT';
    case BILLABLE_WEIGHT = 'BILLABLE_WEIGHT';
    case PARCEL_COUNT = 'PARCEL_COUNT';
    case DECLARED_VALUE = 'DECLARED_VALUE';
    case COD_AMOUNT = 'COD_AMOUNT';
}
