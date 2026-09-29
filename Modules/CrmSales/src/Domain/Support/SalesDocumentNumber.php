<?php

declare(strict_types=1);

namespace Modules\CrmSales\Domain\Support;

use Modules\CrmSales\Domain\Enums\SalesDocumentType;

/**
 * CRM numbering for sales documents, deliberately apart from the consignment number ranges. An operator
 * may always name the document themselves; this is only what an unnamed one is called.
 */
final class SalesDocumentNumber
{
    private const PREFIXES = [
        SalesDocumentType::ESTIMATE->value => 'ES',
        SalesDocumentType::PROPOSAL->value => 'PR',
        SalesDocumentType::PROFORMA->value => 'PF',
    ];

    /** The stem every document of one type and year shares, for example `PR-2026-`. */
    public static function stem(SalesDocumentType $type, int $year): string
    {
        return self::PREFIXES[$type->value].'-'.$year.'-';
    }

    /** The whole number, for example `PR-2026-0012`; the counter runs per tenant, type and year. */
    public static function format(SalesDocumentType $type, int $year, int $sequence): string
    {
        return self::stem($type, $year).str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
