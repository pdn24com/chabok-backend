<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Consignment\Infrastructure\Pricing\LegacyQuoteNormalizer;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use PHPUnit\Framework\TestCase;

final class LegacyQuoteNormalizerTest extends TestCase
{
    public function test_it_normalizes_the_exact_sanitized_provider_fixture(): void
    {
        $fixture = json_decode(
            (string) file_get_contents(dirname(__DIR__).'/Fixtures/LegacyPricing/quote-success.json'),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $options = (new LegacyQuoteNormalizer)->normalize($fixture);

        self::assertCount(2, $options);
        self::assertTrue($options[0]->available);
        self::assertSame('7', $options[0]->externalMethodCode);
        self::assertSame(2500, $options[0]->totalAmount);
        self::assertSame('2026-08-01', $options[0]->deliveryWindows[0]->gregorianDate);
        self::assertFalse($options[1]->available);
    }

    public function test_it_normalizes_available_and_unavailable_options_with_exact_irr_lines(): void
    {
        $options = (new LegacyQuoteNormalizer)->normalize([
            'result' => true,
            'objects' => [
                [
                    'result' => true,
                    'method_no' => 7,
                    'method_name' => 'Express',
                    'icon' => null,
                    'currency' => 'IRR',
                    'quote' => '2500',
                    'deliveryTimeWindow' => [[
                        'gregorian' => '2026-08-01',
                        'jalali' => '۱۴۰۵/۰۵/۱۰',
                        'week' => 'شنبه',
                        'month' => 'مرداد',
                        'time' => ['11:00 - 13:00'],
                    ]],
                    'price' => [
                        'zone' => '2',
                        'fld_Manual_Cost' => 1000,
                        'fld_Pack_Cost' => '500',
                        'fld_Charge_Cost' => 1000,
                        'fld_Manual_Insurance' => 0,
                        'fld_Lab_Cost' => 0,
                        'fld_Agency_Cost_From' => 0,
                        'fld_Agency_Cost' => 0,
                        'fld_Manual_VAT' => 0,
                        'fld_Total_Cost' => 2500,
                        'price_list' => '4',
                        'min_ins' => '100',
                    ],
                ],
                [
                    'result' => false,
                    'message' => 'provider-localized text',
                    'method_no' => '8',
                    'method_name' => 'Unavailable',
                ],
            ],
        ]);

        self::assertCount(2, $options);
        self::assertTrue($options[0]->available);
        self::assertSame(2500, $options[0]->totalAmount);
        self::assertSame(2500, array_sum(array_column($options[0]->chargeLines, 'amount')));
        self::assertSame('2026-08-01', $options[0]->deliveryWindows[0]->gregorianDate);
        self::assertSame('۱۴۰۵/۰۵/۱۰', $options[0]->deliveryWindows[0]->jalaliDisplayDate);
        self::assertSame('شنبه', $options[0]->deliveryWindows[0]->persianWeekdayLabel);
        self::assertSame('مرداد', $options[0]->deliveryWindows[0]->persianMonthLabel);
        self::assertSame(['11:00 - 13:00'], $options[0]->deliveryWindows[0]->timeRanges);
        self::assertFalse($options[1]->available);
        self::assertSame('Pricing method unavailable.', $options[1]->unavailableReason);
        self::assertStringNotContainsString('localized', $options[1]->unavailableReason);
    }

    public function test_it_rejects_fractional_negative_overflowing_and_mismatched_money(): void
    {
        foreach (['1.5', '-1', '999999999999999999999999'] as $invalid) {
            try {
                (new LegacyQuoteNormalizer)->normalize($this->payload($invalid, $invalid, $invalid));
                self::fail("Expected {$invalid} to be rejected.");
            } catch (ApiException $exception) {
                self::assertSame(ApiErrorCode::PricingUnavailable, $exception->errorCode);
            }
        }

        $this->expectException(ApiException::class);
        (new LegacyQuoteNormalizer)->normalize($this->payload(100, 101, 100));
    }

    public function test_zero_available_options_is_a_business_rejection(): void
    {
        try {
            (new LegacyQuoteNormalizer)->normalize([
                'result' => true,
                'objects' => [[
                    'result' => false,
                    'method_no' => '9',
                    'method_name' => 'Unavailable',
                ]],
            ]);
            self::fail('Expected no available option rejection.');
        } catch (ApiException $exception) {
            self::assertSame(ApiErrorCode::PricingRejected, $exception->errorCode);
            self::assertSame(422, $exception->httpStatus);
        }
    }

    /** @return array<string, mixed> */
    private function payload(mixed $quote, mixed $total, mixed $component): array
    {
        return [
            'result' => true,
            'objects' => [[
                'result' => true,
                'method_no' => '1',
                'method_name' => 'Method',
                'currency' => 'IRR',
                'quote' => $quote,
                'deliveryTimeWindow' => [],
                'price' => [
                    'zone' => '1',
                    'fld_Manual_Cost' => $component,
                    'fld_Pack_Cost' => 0,
                    'fld_Charge_Cost' => 0,
                    'fld_Manual_Insurance' => 0,
                    'fld_Lab_Cost' => 0,
                    'fld_Agency_Cost_From' => 0,
                    'fld_Agency_Cost' => 0,
                    'fld_Manual_VAT' => 0,
                    'fld_Total_Cost' => $total,
                    'price_list' => '1',
                    'min_ins' => 0,
                ],
            ]],
        ];
    }
}
