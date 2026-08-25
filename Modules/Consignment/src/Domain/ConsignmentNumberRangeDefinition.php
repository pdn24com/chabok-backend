<?php

declare(strict_types=1);

namespace Modules\Consignment\Domain;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final class ConsignmentNumberRangeDefinition
{
    /** @param array<string, mixed> $input @return array<string, mixed> */
    public function validate(array $input): array
    {
        $prefix = (string) ($input['numeric_prefix'] ?? '');
        $start = (string) ($input['serial_start'] ?? '');
        $end = (string) ($input['serial_end'] ?? '');
        if (! preg_match('/^[0-9]+$/D', $prefix) || ! preg_match('/^[0-9]+$/D', $start) || ! preg_match('/^[0-9]+$/D', $end)) {
            throw new ApiException(ApiErrorCode::ConsignmentNumberInvalidFormat, 422, 'Prefix and serial boundaries must contain ASCII digits only.');
        }

        $totalLength = $input['total_length'] ?? null;
        if (! is_int($totalLength) || $totalLength < 6 || $totalLength > 28) {
            throw new ApiException(ApiErrorCode::ConsignmentNumberInvalidLength, 422, 'Total length must be between 6 and 28.');
        }
        if (strlen($prefix) >= $totalLength) {
            throw new ApiException(ApiErrorCode::ConsignmentNumberPrefixLengthIncompatible, 422, 'Prefix must be shorter than total length.');
        }

        $serialWidth = $totalLength - strlen($prefix);
        if (strlen($start) > $serialWidth || strlen($end) > $serialWidth) {
            throw new ApiException(ApiErrorCode::ConsignmentNumberInvalidBoundaries, 422, 'A serial boundary exceeds the available width.');
        }
        $normalizedStart = DecimalString::pad($start, $serialWidth);
        $normalizedEnd = DecimalString::pad($end, $serialWidth);
        if (strcmp($normalizedStart, $normalizedEnd) > 0) {
            throw new ApiException(ApiErrorCode::ConsignmentNumberInvalidBoundaries, 422, 'Serial start must not exceed serial end.');
        }

        $first = $prefix.$normalizedStart;
        $last = $prefix.$normalizedEnd;
        return [
            'numeric_prefix' => $prefix,
            'total_length' => $totalLength,
            'serial_width' => $serialWidth,
            'serial_start' => $normalizedStart,
            'serial_end' => $normalizedEnd,
            'first_number' => $first,
            'last_number' => $last,
            'total_capacity' => DecimalString::inclusiveCount($normalizedStart, $normalizedEnd),
            'sample_first_values' => $this->samples($prefix, $normalizedStart, $normalizedEnd, true),
            'sample_final_values' => $this->samples($prefix, $normalizedStart, $normalizedEnd, false),
        ];
    }

    /** @return list<string> */
    private function samples(string $prefix, string $start, string $end, bool $fromStart): array
    {
        $values = [];
        $cursor = $fromStart ? $start : $end;
        for ($index = 0; $index < 3; $index++) {
            if (strcmp($cursor, $start) < 0 || strcmp($cursor, $end) > 0) break;
            $values[] = $prefix.$cursor;
            if (($fromStart && $cursor === $end) || (! $fromStart && $cursor === $start)) break;
            $cursor = $fromStart ? DecimalString::pad(DecimalString::increment($cursor), strlen($start)) : DecimalString::pad(DecimalString::decrement($cursor), strlen($start));
        }
        if (! $fromStart) $values = array_reverse($values);
        return $values;
    }
}
