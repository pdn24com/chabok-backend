<?php

declare(strict_types=1);

namespace Modules\Consignment\Domain\Support;

final class DecimalString
{
    public static function compare(string $left, string $right): int
    {
        $left = self::trim($left);
        $right = self::trim($right);

        return strlen($left) <=> strlen($right) ?: strcmp($left, $right);
    }

    public static function increment(string $value): string
    {
        $digits = str_split($value);
        for ($index = count($digits) - 1; $index >= 0; $index--) {
            if ($digits[$index] !== '9') {
                $digits[$index] = chr(ord($digits[$index]) + 1);

                return implode('', $digits);
            }
            $digits[$index] = '0';
        }

        return '1'.implode('', $digits);
    }

    public static function decrement(string $value): string
    {
        $digits = str_split($value);
        for ($index = count($digits) - 1; $index >= 0; $index--) {
            if ($digits[$index] !== '0') {
                $digits[$index] = chr(ord($digits[$index]) - 1);

                return self::trim(implode('', $digits));
            }
            $digits[$index] = '9';
        }

        return '0';
    }

    public static function subtract(string $left, string $right): string
    {
        $leftDigits = str_split(self::trim($left));
        $rightDigits = str_split(str_pad(self::trim($right), count($leftDigits), '0', STR_PAD_LEFT));
        $borrow = 0;
        $result = [];
        for ($index = count($leftDigits) - 1; $index >= 0; $index--) {
            $digit = ord($leftDigits[$index]) - 48 - (ord($rightDigits[$index]) - 48) - $borrow;
            if ($digit < 0) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }
            $result[] = chr(48 + $digit);
        }

        return self::trim(implode('', array_reverse($result)));
    }

    public static function inclusiveCount(string $start, string $end): string
    {
        return self::increment(self::subtract($end, $start));
    }

    public static function pad(string $value, int $width): string
    {
        return str_pad($value, $width, '0', STR_PAD_LEFT);
    }

    private static function trim(string $value): string
    {
        $trimmed = ltrim($value, '0');

        return $trimmed === '' ? '0' : $trimmed;
    }
}
