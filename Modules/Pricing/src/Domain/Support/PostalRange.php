<?php

declare(strict_types=1);

namespace Modules\Pricing\Domain\Support;

final class PostalRange
{
    public static function normalize(string $value): string
    {
        return strtr($value, array_combine(preg_split('//u', '۰۱۲۳۴۵۶۷۸۹٠١٢٣٤٥٦٧٨٩', -1, PREG_SPLIT_NO_EMPTY), str_split('01234567890123456789')));
    }

    public static function valid(string $from, string $to): bool
    {
        return preg_match('/^[0-9]{10}$/D', $from) === 1 && preg_match('/^[0-9]{10}$/D', $to) === 1 && strcmp($from, $to) <= 0;
    }
}
