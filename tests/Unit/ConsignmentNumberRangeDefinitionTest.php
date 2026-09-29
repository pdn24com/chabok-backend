<?php

declare(strict_types=1);

namespace Tests\Unit;

use Modules\Consignment\Application\Services\ConsignmentNumberRangeDefinition;
use Modules\Consignment\Domain\Exceptions\InvalidNumberRange;
use Modules\Consignment\Domain\Support\DecimalString;
use Modules\Consignment\Domain\ValueObjects\NumberRangeInput;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class ConsignmentNumberRangeDefinitionTest extends TestCase
{
    public function test_preserves_leading_zero_prefix_and_normalizes_serials(): void
    {
        $preview = (new ConsignmentNumberRangeDefinition)->validate(NumberRangeInput::fromValidated([
            'numeric_prefix' => '001', 'total_length' => 10, 'serial_start' => '1', 'serial_end' => '9999999',
        ]));
        self::assertSame('0010000001', $preview->firstNumber);
        self::assertSame('0019999999', $preview->lastNumber);
        self::assertSame('9999999', $preview->totalCapacity);
    }

    public function test_supports_minimum_and_maximum_lengths_and_capacity_beyond_safe_integer(): void
    {
        $minimum = (new ConsignmentNumberRangeDefinition)->validate(NumberRangeInput::fromValidated([
            'numeric_prefix' => '1', 'total_length' => 6, 'serial_start' => '0', 'serial_end' => '9',
        ]));
        self::assertSame(6, strlen($minimum->firstNumber));
        $single = (new ConsignmentNumberRangeDefinition)->validate(NumberRangeInput::fromValidated([
            'numeric_prefix' => '12345', 'total_length' => 6, 'serial_start' => '0', 'serial_end' => '0',
        ]));
        self::assertSame(['123450'], $single->sampleFirstValues);
        self::assertSame(['123450'], $single->sampleFinalValues);

        $maximum = (new ConsignmentNumberRangeDefinition)->validate(NumberRangeInput::fromValidated([
            'numeric_prefix' => '001', 'total_length' => 28, 'serial_start' => '0', 'serial_end' => str_repeat('9', 25),
        ]));
        self::assertSame(28, strlen($maximum->lastNumber));
        self::assertSame('1'.str_repeat('0', 25), $maximum->totalCapacity);
    }

    public function test_decimal_increment_is_exact_without_numeric_casts(): void
    {
        self::assertSame('10000000000000000000000000', DecimalString::increment('9999999999999999999999999'));
        self::assertSame('0000000000000000000000010', DecimalString::pad(DecimalString::increment('0000000000000000000000009'), 25));
    }

    #[DataProvider('invalidDefinitions')]
    public function test_rejects_invalid_definitions(array $input, ApiErrorCode $code): void
    {
        try {
            (new ConsignmentNumberRangeDefinition)->validate(NumberRangeInput::fromValidated($input));
            self::fail('Invalid range must be rejected.');
        } catch (InvalidNumberRange $exception) {
            self::assertSame($code, $exception->errorCode);
        }
    }

    /** @return iterable<string,array{array<string,mixed>,ApiErrorCode}> */
    public static function invalidDefinitions(): iterable
    {
        yield 'non digits' => [['numeric_prefix' => '12A', 'total_length' => 10, 'serial_start' => '1', 'serial_end' => '9'], ApiErrorCode::ConsignmentNumberInvalidFormat];
        yield 'short total' => [['numeric_prefix' => '1', 'total_length' => 5, 'serial_start' => '1', 'serial_end' => '9'], ApiErrorCode::ConsignmentNumberInvalidLength];
        yield 'prefix consumes length' => [['numeric_prefix' => '123456', 'total_length' => 6, 'serial_start' => '1', 'serial_end' => '9'], ApiErrorCode::ConsignmentNumberPrefixLengthIncompatible];
        yield 'boundary too wide' => [['numeric_prefix' => '123', 'total_length' => 6, 'serial_start' => '0001', 'serial_end' => '9'], ApiErrorCode::ConsignmentNumberInvalidBoundaries];
        yield 'reversed boundaries' => [['numeric_prefix' => '123', 'total_length' => 6, 'serial_start' => '100', 'serial_end' => '99'], ApiErrorCode::ConsignmentNumberInvalidBoundaries];
    }
}
