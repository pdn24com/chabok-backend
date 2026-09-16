<?php

declare(strict_types=1);

namespace Modules\Consignment\Presentation\Http\Requests;

use Modules\Foundation\Presentation\Http\StrictPayload;
use Illuminate\Validation\ValidationException;

final class EditConsignmentRequest extends ConsignmentInputRequest
{
    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:1'],
            'change_reason' => ['required', 'string', 'max:160'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'payer' => ['sometimes', 'in:SENDER,RECEIVER,VENDOR'],
            'payment_method' => ['sometimes', 'in:CASH,CREDIT,COD'],
            'parcels' => ['sometimes', 'array', 'min:1', 'max:100'],
            'parcels.*.parcel_id' => ['required', 'uuid', 'distinct'],
            'parcels.*.content_description' => ['present', 'nullable', 'string', 'max:500'],
            'parcels.*.weight_kg' => ['present', 'nullable', 'numeric', 'gt:0'],
            'parcels.*.width_cm' => ['present', 'nullable', 'numeric', 'gt:0'],
            'parcels.*.length_cm' => ['present', 'nullable', 'numeric', 'gt:0'],
            'parcels.*.height_cm' => ['present', 'nullable', 'numeric', 'gt:0'],
            ...$this->contactRules('sender', false),
            ...$this->contactRules('receiver', false),
            'service_type_id' => ['sometimes', 'uuid'],
            'shipping_method_id' => ['sometimes', 'uuid'],
            'service_offering_id' => ['sometimes', 'nullable', 'uuid'],
            'service_offering_version_id' => ['sometimes', 'nullable', 'uuid'],
            'selected_option_version_ids' => ['sometimes', 'array'],
            'selected_option_version_ids.*' => ['uuid'],
            'pickup_service_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'pickup_window_code' => ['sometimes', 'nullable', 'string', 'max:80'],
            'delivery_window_code' => ['sometimes', 'nullable', 'string', 'max:80'],
            'pickup_commitment_at' => ['sometimes', 'nullable', 'date'],
            'delivery_commitment_at' => ['sometimes', 'nullable', 'date'],
            'weight_kg' => ['sometimes', 'numeric', 'gt:0'],
            'width_cm' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'length_cm' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'height_cm' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'declared_value_amount' => ['sometimes', 'integer', 'min:0'],
            'insurance_enabled' => ['sometimes', 'boolean'],
            'insurance_value_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'cod_enabled' => ['sometimes', 'boolean'],
            'cod_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            ...$this->acceptedQuoteRules(false),
        ];
    }

    protected function prepareForValidation(): void
    {
        $allowed = [
            'expected_version',
            'change_reason',
            'note',
            'sender',
            'receiver',
            'service_type_id',
            'shipping_method_id',
            'service_offering_id',
            'service_offering_version_id',
            'selected_option_version_ids',
            'pickup_service_date',
            'pickup_window_code',
            'delivery_window_code',
            'pickup_commitment_at',
            'delivery_commitment_at',
            'weight_kg',
            'width_cm',
            'length_cm',
            'height_cm',
            'declared_value_amount',
            'insurance_enabled',
            'insurance_value_amount',
            'cod_enabled',
            'cod_amount',
            'accepted_quote',
            'payer',
            'payment_method',
            'parcels',
        ];
        StrictPayload::assertOnly($this, $allowed);
        $this->assertContactOnly($this->input('sender'), 'sender');
        $this->assertContactOnly($this->input('receiver'), 'receiver');
        $this->assertAcceptedQuoteOnly($this->input('accepted_quote'));
        if (is_array($this->input('parcels'))) {
            StrictPayload::assertItemsOnly($this->input('parcels'), [...self::PARCEL_FIELDS, 'parcel_id'], 'parcels');
        }
    }

    protected function passedValidation(): void
    {
        $input = $this->validated();
        if (count(array_diff(array_keys($input), ['expected_version', 'change_reason', 'note', 'accepted_quote'])) === 0) {
            throw ValidationException::withMessages(['changes' => ['At least one editable field is required.']]);
        }
    }
}
