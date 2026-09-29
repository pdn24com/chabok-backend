<?php

declare(strict_types=1);

namespace Modules\CrmFinance\Tests\Unit;

use Modules\CrmFinance\Domain\Support\AccountNumberMask;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AccountNumberMaskTest extends TestCase
{
    #[DataProvider('numbers')]
    public function test_a_number_keeps_only_the_ends_that_tell_two_accounts_apart(string $kind, ?string $number, ?string $expected): void
    {
        self::assertSame($expected, AccountNumberMask::$kind($number));
    }

    public static function numbers(): array
    {
        return [
            // An IBAN keeps its country prefix and its last four digits, a card its issuer prefix as well.
            'iranian iban' => ['iban', 'IR820120000000001234567890', 'IR********************7890'],
            'card number' => ['cardNumber', '6104337899521102', '610433******1102'],
            'account number' => ['accountNo', '1234567890', '******7890'],
            // A number too short to keep both ends is masked whole rather than half revealed.
            'short iban' => ['iban', 'IR8201', '******'],
            'short card' => ['cardNumber', '6104337899', '**********'],
            'short account number' => ['accountNo', '1234', '****'],
            'nothing stored' => ['iban', null, null],
            'empty string' => ['accountNo', '', null],
        ];
    }
}
