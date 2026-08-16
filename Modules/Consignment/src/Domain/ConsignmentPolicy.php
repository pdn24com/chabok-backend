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

    /** @param array<string,mixed> $input */
    public function assertPilotCreate(array $input): void
    {
        if (($input['insurance_enabled'] ?? false) !== true || (int) ($input['insurance_value_amount'] ?? -1) !== (int) ($input['declared_value_amount'] ?? 0)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Declared-value insurance is mandatory for new pilot Consignments.', details: ['reason_code' => 'MANDATORY_INSURANCE_REQUIRED']);
        }
        if (! in_array($input['payer'] ?? null, ['SENDER', 'RECEIVER'], true)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The payer is not available for new pilot Consignments.', details: ['reason_code' => 'PILOT_PAYER_INVALID']);
        }
        if (! in_array($input['payment_method'] ?? null, ['CASH', 'CREDIT'], true)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The payment method is not available for new pilot Consignments.', details: ['reason_code' => 'PILOT_PAYMENT_METHOD_INVALID']);
        }
        foreach ((array) ($input['parcels'] ?? []) as $index => $parcel) {
            if (trim((string) ($parcel['content_description'] ?? '')) === '') {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Every Parcel requires a content description.', details: ['reason_code' => 'PARCEL_CONTENT_REQUIRED', 'parcel_index' => $index]);
            }
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
