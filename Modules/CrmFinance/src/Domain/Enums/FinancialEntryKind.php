<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Domain\Enums;

/**
 * What one manually recorded financial line means. This is not a general ledger: a BALANCE_SNAPSHOT is a
 * reading of the account at a moment and must never be summed with the REVENUE and RECEIPT flow.
 */
enum FinancialEntryKind: string
{
    case REVENUE = 'REVENUE';
    case RECEIPT = 'RECEIPT';
    case DIRECT_COST = 'DIRECT_COST';
    case OPENING_BALANCE = 'OPENING_BALANCE';
    case BALANCE_SNAPSHOT = 'BALANCE_SNAPSHOT';
    case ADJUSTMENT = 'ADJUSTMENT';
}
