<?php

declare(strict_types=1);

namespace Modules\Consignment\Presentation\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Modules\Foundation\Presentation\Http\StrictPayload;

abstract class ConsignmentInputRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }
    protected const DRAFT_FIELDS = [
        'sender',
        'receiver',
        'delivery_node_id',
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
        'payer',
        'payment_method',
        'parcels',
    ];
    protected const CONTACT_FIELDS = [
        'address_book_entry_id',
        'contact_name',
        'mobile',
        'phone',
        'address_text',
        'country',
        'state',
        'city',
        'city_id',
        'postal_code',
        'latitude',
        'longitude',
    ];
    protected const PARCEL_FIELDS = ['content_description', 'weight_kg', 'width_cm', 'length_cm', 'height_cm'];

    protected function draftRules(): array
    {
        return [
            ...$this->contactRules('sender', true),
            ...$this->contactRules('receiver', true),
            'delivery_node_id' => ['sometimes', 'nullable', 'uuid'],
            'service_type_id' => ['required_without:service_offering_id', 'nullable', 'uuid'],
            'shipping_method_id' => ['required_without:service_offering_id', 'nullable', 'uuid'],
            'service_offering_id' => ['sometimes', 'nullable', 'uuid'],
            'service_offering_version_id' => ['sometimes', 'nullable', 'uuid'],
            'selected_option_version_ids' => ['sometimes', 'array'],
            'selected_option_version_ids.*' => ['uuid'],
            'pickup_service_date' => ['sometimes', 'nullable', 'date_format:Y-m-d'],
            'pickup_window_code' => ['sometimes', 'nullable', 'string', 'max:80'],
            'delivery_window_code' => ['sometimes', 'nullable', 'string', 'max:80'],
            'pickup_commitment_at' => ['sometimes', 'nullable', 'date'],
            'delivery_commitment_at' => ['sometimes', 'nullable', 'date'],
            'weight_kg' => ['required', 'numeric', 'gt:0'],
            'width_cm' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'length_cm' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'height_cm' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'declared_value_amount' => ['required', 'integer', 'min:0'],
            'insurance_enabled' => ['required', 'boolean'],
            'insurance_value_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'cod_enabled' => ['required', 'boolean'],
            'cod_amount' => ['sometimes', 'nullable', 'integer', 'min:0'],
            'payer' => ['required', 'in:SENDER,RECEIVER,VENDOR'],
            'payment_method' => ['required', 'in:CASH,CREDIT,COD'],
            'parcels' => ['required', 'array', 'min:1', 'max:100'],
            'parcels.*.content_description' => ['sometimes', 'nullable', 'string', 'max:500'],
            'parcels.*.weight_kg' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'parcels.*.width_cm' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'parcels.*.length_cm' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
            'parcels.*.height_cm' => ['sometimes', 'nullable', 'numeric', 'gt:0'],
        ];
    }

    protected function contactRules(string $prefix, bool $required): array
    {
        $presence = $required ? 'required' : 'sometimes';
        return [
            $prefix => [$presence, 'array'],
            "{$prefix}.address_book_entry_id" => ['sometimes', 'nullable', 'uuid'],
            "{$prefix}.contact_name" => [$required ? 'required' : 'sometimes', 'string', 'max:200'],
            "{$prefix}.mobile" => [$required ? 'required' : 'sometimes', 'string', 'max:32'],
            "{$prefix}.phone" => ['sometimes', 'nullable', 'string', 'max:32'],
            "{$prefix}.address_text" => [$required ? 'required' : 'sometimes', 'string', 'max:1000'],
            "{$prefix}.country" => ['sometimes', 'nullable', 'string', 'max:120'],
            "{$prefix}.state" => ['sometimes', 'nullable', 'string', 'max:160'],
            "{$prefix}.city" => ['sometimes', 'nullable', 'string', 'max:160'],
            "{$prefix}.city_id" => [$required ? 'required' : 'sometimes', 'uuid'],
            "{$prefix}.postal_code" => ['sometimes', 'nullable', 'string', 'max:32'],
            "{$prefix}.latitude" => ['sometimes', 'nullable', 'numeric', 'between:-90,90'],
            "{$prefix}.longitude" => ['sometimes', 'nullable', 'numeric', 'between:-180,180'],
        ];
    }

    protected function acceptedQuoteRules(bool $required = true): array
    {
        return [
            'accepted_quote' => [$required ? 'required' : 'sometimes', 'array'],
            'accepted_quote.quote_id' => [$required ? 'required' : 'required_with:accepted_quote', 'uuid'],
            'accepted_quote.quote_version' => [$required ? 'required' : 'required_with:accepted_quote', 'integer', 'min:1'],
            'accepted_quote.option_id' => [$required ? 'required' : 'required_with:accepted_quote', 'uuid'],
        ];
    }

    protected function assertNestedPayload(Request $request, bool $accepted = false): void
    {
        $this->assertContactOnly($request->input('sender'), 'sender');
        $this->assertContactOnly($request->input('receiver'), 'receiver');
        if (is_array($request->input('parcels'))) {
            StrictPayload::assertItemsOnly($request->input('parcels'), self::PARCEL_FIELDS, 'parcels');
        }
        if ($accepted) {
            $this->assertAcceptedQuoteOnly($request->input('accepted_quote'));
        }
    }

    protected function assertContactOnly(mixed $contact, string $field): void
    {
        if (is_array($contact)) {
            StrictPayload::assertItemsOnly([$contact], self::CONTACT_FIELDS, $field);
        }
    }

    protected function assertAcceptedQuoteOnly(mixed $quote): void
    {
        if (is_array($quote)) {
            StrictPayload::assertItemsOnly([$quote], ['quote_id', 'quote_version', 'option_id'], 'accepted_quote');
        }
    }

    public function normalizePilotCreate(array $input): array
    {
        if (($input['insurance_enabled'] ?? null) !== true) {
            throw ValidationException::withMessages(['insurance_enabled' => ['Insurance is mandatory for new pilot Consignments.']]);
        }
        if (!in_array($input['payer'] ?? null, ['SENDER', 'RECEIVER'], true)) {
            throw ValidationException::withMessages(['payer' => ['Only sender or receiver payer is available for new pilot Consignments.']]);
        }
        if (!in_array($input['payment_method'] ?? null, ['CASH', 'CREDIT'], true)) {
            throw ValidationException::withMessages(['payment_method' => ['Only cash or credit is available for new pilot Consignments.']]);
        }
        $input['insurance_enabled'] = true;
        $input['insurance_value_amount'] = (int) $input['declared_value_amount'];
        return $input;
    }
}
