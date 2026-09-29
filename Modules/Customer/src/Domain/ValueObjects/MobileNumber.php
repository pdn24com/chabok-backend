<?php

declare(strict_types=1);

namespace Modules\Customer\Domain\ValueObjects;

/**
 * A mobile contact point keeps the number exactly as the operator typed it and, next to it, one
 * canonical form in +<country><subscriber> notation so later lookups never re-parse the input.
 */
final readonly class MobileNumber
{
    private const PERSIAN_DIGITS = [
        '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
        '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
        '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
        '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
    ];

    private function __construct(public string $value, public string $normalized) {}

    /**
     * Accepts an Iranian mobile number in any local spelling (09xxxxxxxxx, 9xxxxxxxxx) and a number of
     * any other country only in international spelling (+ or 00 followed by 7 to 15 digits). Persian and
     * Arabic digits, spaces, dashes, dots and parentheses are accepted everywhere. Returns null when the
     * input is not a usable mobile number, so the caller decides which error the operator sees.
     */
    public static function tryFrom(string $value): ?self
    {
        $entered = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);
        if ($entered === '') {
            return null;
        }
        $compact = preg_replace('/[\s\-().]+/u', '', strtr($entered, self::PERSIAN_DIGITS)) ?? '';

        if (preg_match('/^(?:\+|00)(\d{7,15})$/D', $compact, $international) === 1) {
            return new self($entered, '+'.$international[1]);
        }
        if (preg_match('/^0?(9\d{9})$/D', $compact, $iranian) === 1) {
            return new self($entered, '+98'.$iranian[1]);
        }

        return null;
    }
}
