<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Enums;

enum PricingFact: string
{
    case ACTUAL_WEIGHT = 'actual_weight_kg';
    case BILLABLE_WEIGHT = 'billable_weight_kg';
    case PARCEL_COUNT = 'parcel_count';
    case DECLARED_VALUE = 'declared_value_amount';
    case COD_AMOUNT = 'cod_amount';
    case INSURANCE_ENABLED = 'insurance_enabled';
    case COD_ENABLED = 'cod_enabled';
    case REMOTE_AREA = 'remote_area';
    case WEIGHT_EVIDENCE = 'weight_evidence';
}
