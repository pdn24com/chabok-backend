<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Domain\Support;

/**
 * Bank identifiers are stored whole so a transfer can be made from them, but they leave the system only
 * as a mask: enough of the number for an operator to tell two accounts apart, never enough to use one.
 * The masks are built from the stored characters alone, so a number of any length stays readable.
 */
final class AccountNumberMask
{
    /** An IBAN keeps its two-letter country prefix and its last four digits: IR**************1234. */
    public static function iban(?string $iban): ?string
    {
        return self::keeping($iban, leading: 2, trailing: 4);
    }

    /** A card keeps the six-digit issuer prefix and the last four digits: 610433******1102. */
    public static function cardNumber(?string $cardNumber): ?string
    {
        return self::keeping($cardNumber, leading: 6, trailing: 4);
    }

    /** An account number keeps nothing but its last four digits, because it carries no prefix worth showing. */
    public static function accountNo(?string $accountNo): ?string
    {
        return self::keeping($accountNo, leading: 0, trailing: 4);
    }

    /**
     * Keeps the first and last characters and replaces everything between them with asterisks. A number
     * too short to keep both ends is masked whole rather than half revealed.
     */
    private static function keeping(?string $number, int $leading, int $trailing): ?string
    {
        if ($number === null || $number === '') {
            return null;
        }
        $length = mb_strlen($number);
        if ($length <= $leading + $trailing) {
            return str_repeat('*', $length);
        }

        return mb_substr($number, 0, $leading)
            .str_repeat('*', $length - $leading - $trailing)
            .mb_substr($number, -$trailing);
    }
}
