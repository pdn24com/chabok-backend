<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Consignment\Domain\ConsignmentPolicy;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConsignmentPilotPolicyTest extends TestCase
{
    #[DataProvider('invalidDrafts')]
    public function test_new_pilot_create_rejects_legacy_or_incomplete_commercial_input(array $changes, string $reason): void
    {
        $draft = array_replace_recursive($this->validDraft(), $changes);
        try {
            (new ConsignmentPolicy())->assertPilotCreate($draft);
            self::fail('Invalid pilot input must fail closed.');
        } catch (ApiException $exception) {
            self::assertSame(ApiErrorCode::ValidationError, $exception->errorCode);
            self::assertSame($reason, $exception->details['reason_code']);
        }
    }

    public function test_declared_value_is_the_only_accepted_insurance_basis(): void
    {
        (new ConsignmentPolicy())->assertPilotCreate($this->validDraft());
        self::addToAssertionCount(1);
    }

    public function test_parcel_content_description_is_optional(): void
    {
        foreach (['', null] as $content) {
            $draft = $this->validDraft();
            $draft['parcels'][0]['content_description'] = $content;
            (new ConsignmentPolicy())->assertPilotCreate($draft);
        }
        self::addToAssertionCount(2);
    }

    public static function invalidDrafts(): iterable
    {
        yield 'insurance disabled' => [['insurance_enabled' => false, 'insurance_value_amount' => null], 'MANDATORY_INSURANCE_REQUIRED'];
        yield 'separate insured value' => [['insurance_value_amount' => 999], 'MANDATORY_INSURANCE_REQUIRED'];
        yield 'vendor payer' => [['payer' => 'VENDOR'], 'PILOT_PAYER_INVALID'];
        yield 'cod payment method' => [['payment_method' => 'COD'], 'PILOT_PAYMENT_METHOD_INVALID'];
    }

    /** @return array<string,mixed> */
    private function validDraft(): array
    {
        return [
            'declared_value_amount' => 290000000,
            'insurance_enabled' => true,
            'insurance_value_amount' => 290000000,
            'payer' => 'SENDER',
            'payment_method' => 'CASH',
            'parcels' => [['content_description' => 'قطعات الکترونیکی']],
        ];
    }
}
