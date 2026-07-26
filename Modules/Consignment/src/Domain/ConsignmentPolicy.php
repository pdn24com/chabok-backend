<?php

declare(strict_types=1);

namespace Modules\Consignment\Domain;

use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final class ConsignmentPolicy
{
    /** @param list<string> $editableStatuses */
    public function assertEditable(string $status, array $editableStatuses): void
    {
        if (! in_array($status, $editableStatuses, true)) {
            throw new ApiException(
                ApiErrorCode::ConsignmentNotEditable,
                422,
                'The Consignment can no longer be edited.',
            );
        }
    }

    /** @param array<string, mixed> $input */
    public function assertCommercialConsistency(array $input): void
    {
        $this->assertOptionalAmount($input, 'insurance_enabled', 'insurance_value_amount');
        $this->assertOptionalAmount($input, 'cod_enabled', 'cod_amount');
        $dimensions = [
            $input['width_cm'] ?? null,
            $input['length_cm'] ?? null,
            $input['height_cm'] ?? null,
        ];
        $present = array_filter($dimensions, static fn ($value): bool => $value !== null);
        if ($present !== [] && count($present) !== 3) {
            throw new ApiException(
                ApiErrorCode::ValidationError,
                422,
                'All dimensions must be provided together.',
                ['dimensions' => ['Width, length, and height are required together.']],
            );
        }
    }

    /** @param array<string, mixed> $input */
    private function assertOptionalAmount(array $input, string $enabledKey, string $amountKey): void
    {
        $enabled = (bool) ($input[$enabledKey] ?? false);
        $amount = $input[$amountKey] ?? null;
        if (($enabled && $amount === null) || (! $enabled && $amount !== null)) {
            throw new ApiException(
                ApiErrorCode::ValidationError,
                422,
                'Commercial amount and selection do not agree.',
                [$amountKey => ['The amount must be present only when enabled.']],
            );
        }
    }
}
