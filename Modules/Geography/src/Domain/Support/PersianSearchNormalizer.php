<?php

declare(strict_types=1);

namespace Modules\Geography\Domain\Support;

final class PersianSearchNormalizer
{
    public function normalize(string $value): string
    {
        $value = strtr($value, [
            'ك' => 'ک',
            // Arabic kaf -> Persian kaf
            'ي' => 'ی',
            // Arabic yeh -> Persian yeh
            'ى' => 'ی',
            // alef maqsura -> Persian yeh
            'ۀ' => 'ه',
            // heh with yeh above -> heh
            'ة' => 'ه',
            // teh marbuta -> heh
            '‌' => ' ',
            // ZWNJ is a search word boundary
            '‍' => '',
            'ـ' => '',
        ]);
        $value = preg_replace('/[\x{064B}-\x{065F}\x{0670}\x{06D6}-\x{06ED}]/u', '', $value) ?? $value;
        $value = preg_replace('/\s+/u', ' ', trim($value)) ?? trim($value);

        return mb_strtolower($value);
    }
}
