<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Services;

use Modules\Consignment\Application\Contracts\ConsignmentNumberRangeDefinitionInterface;
use Modules\Consignment\Domain\Exceptions\InvalidNumberRange;
use Modules\Consignment\Domain\Support\DecimalString;
use Modules\Consignment\Domain\ValueObjects\NumberRangeInput;
use Modules\Consignment\Domain\ValueObjects\NumberRangePreview;
use Modules\Foundation\Domain\Enums\ApiErrorCode;

final class ConsignmentNumberRangeDefinition implements ConsignmentNumberRangeDefinitionInterface
{
    public function validate(NumberRangeInput $input): NumberRangePreview
    {
        $prefix = $input->numericPrefix;
        $start = $input->serialStart;
        $end = $input->serialEnd;
        if (! preg_match('/^[0-9]+$/D', $prefix) || ! preg_match('/^[0-9]+$/D', $start) || ! preg_match('/^[0-9]+$/D', $end)) {
            throw new InvalidNumberRange(ApiErrorCode::ConsignmentNumberInvalidFormat, 'consignment.prefix_and_serial_must_be_ascii_digits');
        }
        $totalLength = $input->totalLength;
        if ($totalLength === null || $totalLength < 6 || $totalLength > 28) {
            throw new InvalidNumberRange(ApiErrorCode::ConsignmentNumberInvalidLength, 'consignment.total_length_must_be_between_6_and_28');
        }
        if (strlen($prefix) >= $totalLength) {
            throw new InvalidNumberRange(ApiErrorCode::ConsignmentNumberPrefixLengthIncompatible, 'consignment.prefix_must_be_shorter_than_total_length');
        }
        $serialWidth = $totalLength - strlen($prefix);
        if (strlen($start) > $serialWidth || strlen($end) > $serialWidth) {
            throw new InvalidNumberRange(ApiErrorCode::ConsignmentNumberInvalidBoundaries, 'consignment.serial_boundary_exceeds_available_width');
        }
        $normalizedStart = DecimalString::pad($start, $serialWidth);
        $normalizedEnd = DecimalString::pad($end, $serialWidth);
        if (strcmp($normalizedStart, $normalizedEnd) > 0) {
            throw new InvalidNumberRange(ApiErrorCode::ConsignmentNumberInvalidBoundaries, 'consignment.serial_start_must_not_exceed_serial_end');
        }
        $first = $prefix.$normalizedStart;
        $last = $prefix.$normalizedEnd;

        return new NumberRangePreview(
            numericPrefix: $prefix,
            totalLength: $totalLength,
            serialWidth: $serialWidth,
            serialStart: $normalizedStart,
            serialEnd: $normalizedEnd,
            firstNumber: $first,
            lastNumber: $last,
            totalCapacity: DecimalString::inclusiveCount($normalizedStart, $normalizedEnd),
            sampleFirstValues: $this->samples($prefix, $normalizedStart, $normalizedEnd, true),
            sampleFinalValues: $this->samples($prefix, $normalizedStart, $normalizedEnd, false),
        );
    }

    /** @return list<string> */
    private function samples(
        string $prefix,
        string $start,
        string $end,
        bool $fromStart,
    ): array {
        $values = [];
        $cursor = $fromStart ? $start : $end;
        for ($index = 0; $index < 3; $index++) {
            if (strcmp($cursor, $start) < 0 || strcmp($cursor, $end) > 0) {
                break;
            }
            $values[] = $prefix.$cursor;
            if ($fromStart && $cursor === $end || ! $fromStart && $cursor === $start) {
                break;
            }
            $cursor = $fromStart ? DecimalString::pad(DecimalString::increment($cursor), strlen($start)) : DecimalString::pad(DecimalString::decrement($cursor), strlen($start));
        }
        if (! $fromStart) {
            $values = array_reverse($values);
        }

        return $values;
    }
}
