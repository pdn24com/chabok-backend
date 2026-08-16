<?php

declare(strict_types=1);

namespace Modules\Geography\Domain;

final class PersianSearchNormalizer
{
    public function normalize(string $value): string
    {
        $value = strtr($value, [
            "\u{0643}" => "\u{06A9}", // Arabic kaf -> Persian kaf
            "\u{064A}" => "\u{06CC}", // Arabic yeh -> Persian yeh
            "\u{0649}" => "\u{06CC}", // alef maqsura -> Persian yeh
            "\u{06C0}" => "\u{0647}", // heh with yeh above -> heh
            "\u{0629}" => "\u{0647}", // teh marbuta -> heh
            "\u{200C}" => ' ',          // ZWNJ is a search word boundary
            "\u{200D}" => '',
            "\u{0640}" => '',           // tatweel
        ]);
        $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return mb_strtolower($value);
    }
}
