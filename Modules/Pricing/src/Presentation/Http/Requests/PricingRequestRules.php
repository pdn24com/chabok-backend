<?php

declare(strict_types=1);

namespace Modules\Pricing\Presentation\Http\Requests;

final class PricingRequestRules
{
    public static function quoteRules(): array
    {
        return [
            'purpose' => ['sometimes', 'in:SALES,PURCHASE,COMMISSION,INTERNAL_TRANSFER'],
            'channel' => ['sometimes', 'in:BRANCH,VENDOR,DRIVER,HQ,API,TRACKING,LEGACY'],
            'as_of_timestamp' => ['nullable', 'date'],
            'service_offering_id' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'service_offering_version_id' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'selected_option_version_ids' => ['array'],
            'selected_option_version_ids.*' => ['integer', 'min:1', 'max:4294967295'],
            'pickup_service_date' => ['nullable', 'date_format:Y-m-d'],
            'pickup_window_code' => ['nullable', 'string', 'max:80'],
            'delivery_window_code' => ['nullable', 'string', 'max:80'],
            'sender' => ['required', 'array'],
            'sender.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'sender.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'sender.city_id' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'sender.postal_code' => ['sometimes', 'nullable', 'string', 'max:32'],
            'receiver' => ['required', 'array'],
            'receiver.latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'receiver.longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'receiver.city_id' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'receiver.postal_code' => ['sometimes', 'nullable', 'string', 'max:32'],
            'parcels' => ['array'],
            'parcels.*.content_description' => ['nullable', 'string', 'max:500'],
            'parcels.*.weight_kg' => ['required', 'numeric', 'gt:0'],
            'parcels.*.width_cm' => ['nullable', 'numeric', 'gt:0'],
            'parcels.*.length_cm' => ['nullable', 'numeric', 'gt:0'],
            'parcels.*.height_cm' => ['nullable', 'numeric', 'gt:0'],
            'weight_kg' => ['nullable', 'numeric', 'gt:0'],
            'width_cm' => ['nullable', 'numeric', 'gt:0'],
            'length_cm' => ['nullable', 'numeric', 'gt:0'],
            'height_cm' => ['nullable', 'numeric', 'gt:0'],
            'declared_value_amount' => ['required', 'integer', 'min:0'],
            'insurance_enabled' => ['required', 'boolean'],
            'cod_enabled' => ['required', 'boolean'],
            'cod_amount' => ['nullable', 'integer', 'min:0'],
        ];
    }

    /** @return array<string,mixed> */
    public static function zoneSetRules(bool $create): array
    {
        $rules = [
            'zones.*.members.*.geometry' => ['required_if:zones.*.members.*.member_type,POLYGON', 'nullable', 'array'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date'],
            'zones' => ['required', 'array', 'min:1'],
            'zones.*.pricing_zone_id' => ['nullable', 'integer', 'min:1', 'max:4294967295', 'distinct'],
            'zones.*.rank' => ['nullable', 'integer', 'min:1', 'distinct'],
            'zones.*.code' => ['required', 'regex:/^[A-Z][A-Z0-9_]{0,79}$/'],
            'zones.*.title' => ['required', 'string', 'max:200'],
            'zones.*.remote_area' => ['sometimes', 'boolean'],
            'zones.*.members' => ['required', 'array', 'min:1'],
            'zones.*.members.*.member_type' => ['required', 'in:EXPLICIT_OVERRIDE,POSTAL_RANGE,CITY,PROVINCE,POLYGON'],
            'zones.*.members.*.reference_value' => ['required_unless:zones.*.members.*.member_type,CITY,PROVINCE,POLYGON', 'nullable', 'string', 'max:200'],
            'zones.*.members.*.city_id' => ['required_if:zones.*.members.*.member_type,CITY', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'zones.*.members.*.province_id' => ['required_if:zones.*.members.*.member_type,PROVINCE', 'nullable', 'integer', 'min:1', 'max:4294967295'],
            'zones.*.members.*.range_end' => ['nullable', 'string', 'max:200'],
        ];
        if ($create) {
            $rules += [
                'code' => ['required', 'regex:/^(?:[0-9]{6}|[A-Z][A-Z0-9_]{1,79})$/'],
                'purpose' => ['required', 'in:SALES,PURCHASE,COMMISSION,INTERNAL_TRANSFER'],
                'title' => ['required', 'string', 'max:200'],
            ];
        }

        return $rules;
    }

    /** @return array<string,mixed> */
    public static function tariffRules(bool $create): array
    {
        $rules = self::matrixRules() + [
            'zone_set_version_id' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'valid_from' => ['nullable', 'date'],
            'valid_to' => ['nullable', 'date'],
            'volumetric_divisor' => ['numeric', 'gt:0'],
            'weight_rounding_step_kg' => ['numeric', 'gt:0'],
            'rounding_mode' => ['in:HALF_UP,HALF_EVEN,CEILING,FLOOR,STEP_UP'],
            'rules' => ['present', 'array', 'max:10000'],
            'rules.*.service_offering_version_id' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'rules.*.service_option_version_id' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'rules.*.charge_type_id' => ['required', 'integer', 'min:1', 'max:4294967295'],
            'rules.*.origin_zone_id' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'rules.*.destination_zone_id' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'rules.*.calculation_method' => ['required', 'in:FIXED,PER_UNIT,SLAB,TIERED,PERCENT,MIN_MAX'],
            'rules.*.basis' => ['sometimes', 'in:FLAT,SHIPMENT,ACTUAL_WEIGHT,BILLABLE_WEIGHT,PARCEL_COUNT,DECLARED_VALUE,COD_AMOUNT'],
            'rules.*.range_from' => ['nullable', 'numeric', 'min:0'],
            'rules.*.range_to' => ['nullable', 'numeric', 'gt:rules.*.range_from'],
            'rules.*.fixed_amount' => ['nullable', 'integer', 'min:0'],
            'rules.*.unit_rate' => ['nullable', 'numeric', 'min:0'],
            'rules.*.percentage_bps' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'rules.*.minimum_amount' => ['nullable', 'integer', 'min:0'],
            'rules.*.maximum_amount' => ['nullable', 'integer', 'min:0'],
            'rules.*.amount_rounding_mode' => ['sometimes', 'in:NONE,CEIL,FLOOR,HALF_UP'],
            'rules.*.amount_rounding_step' => ['nullable', 'integer', 'min:1'],
            'rules.*.basis_charge_codes' => ['nullable', 'array'],
            'rules.*.basis_charge_codes.*' => ['string', 'max:80'],
            'rules.*.conditions' => ['nullable', 'array'],
            'rules.*.priority' => ['sometimes', 'integer', 'min:1', 'max:65535'],
        ];
        if ($create) {
            $rules += [
                'code' => ['nullable', 'regex:/^[0-9]{1,9}$/'],
                'purpose' => ['required', 'in:SALES,PURCHASE,COMMISSION,INTERNAL_TRANSFER'],
                'currency' => ['required', 'in:IRR'],
                'title' => ['required', 'string', 'max:200'],
                'scope_type' => ['in:PLATFORM,TENANT,SEGMENT,CUSTOMER,CONTRACT'],
                'scope_value' => ['nullable', 'string', 'max:120'],
                'priority' => ['integer', 'min:1', 'max:65535'],
            ];
        }
        $rules += [
            'tariff_kind' => ['sometimes', 'in:FREIGHT,SERVICE'],
            'service_charge_type_id' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'matrix_basis' => ['sometimes', 'in:ACTUAL_WEIGHT,BILLABLE_WEIGHT,DECLARED_VALUE,COD_AMOUNT,PARCEL_COUNT'],
            'is_default' => ['sometimes', 'boolean'],
            'service_tariff_family_ids' => ['sometimes', 'array', 'max:30'],
            'service_tariff_family_ids.*' => ['integer', 'min:1', 'max:4294967295', 'distinct'],
        ];

        return $rules;
    }

    public static function matrixRules(): array
    {
        return [
            'zone_policy' => ['sometimes', 'in:DIRECTIONAL,HIGHER_ZONE_RANK'],
            'freight_matrices' => ['sometimes', 'array', 'max:100'],
            'freight_matrices.*.id' => ['required', 'string', 'max:64', 'distinct'],
            'freight_matrices.*.service_offering_version_id' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'freight_matrices.*.service_option_version_id' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'freight_matrices.*.origin_zone_id' => ['nullable', 'integer', 'min:1', 'max:4294967295'],
            'freight_matrices.*.zone_ids' => ['required', 'array', 'min:1', 'max:100'],
            'freight_matrices.*.zone_ids.*' => ['integer', 'min:0', 'max:4294967295'],
            'freight_matrices.*.bands' => ['present', 'array', 'max:500'],
            'freight_matrices.*.bands.*.id' => ['required', 'string', 'max:64'],
            'freight_matrices.*.bands.*.from' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'freight_matrices.*.bands.*.to' => ['required', 'numeric', 'gt:0', 'decimal:0,4'],
            'freight_matrices.*.bands.*.cells' => ['present', 'array', 'max:100'],
            'freight_matrices.*.bands.*.cells.*.id' => ['required', 'string', 'max:64'],
            'freight_matrices.*.bands.*.cells.*.zone_id' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'freight_matrices.*.bands.*.cells.*.state' => ['required', 'in:EMPTY,RATE,UNCOVERED'],
            'freight_matrices.*.bands.*.cells.*.amount' => ['nullable', 'integer', 'min:0', 'max:9007199254740991'],
            'freight_matrices.*.linear_tail' => ['nullable', 'array:id,from,step_kg,cells'],
            'freight_matrices.*.linear_tail.id' => ['required_with:freight_matrices.*.linear_tail', 'string', 'max:64'],
            'freight_matrices.*.linear_tail.from' => ['required_with:freight_matrices.*.linear_tail', 'numeric', 'min:0', 'decimal:0,4'],
            'freight_matrices.*.linear_tail.step_kg' => ['required_with:freight_matrices.*.linear_tail', 'numeric', 'gt:0', 'max:99999999', 'decimal:0,4'],
            'freight_matrices.*.linear_tail.cells' => ['required_with:freight_matrices.*.linear_tail', 'array', 'max:100'],
            'freight_matrices.*.linear_tail.cells.*.id' => ['required', 'string', 'max:64'],
            'freight_matrices.*.linear_tail.cells.*.zone_id' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'freight_matrices.*.linear_tail.cells.*.state' => ['required', 'in:EMPTY,RATE,UNCOVERED'],
            'freight_matrices.*.linear_tail.cells.*.amount' => ['nullable', 'integer', 'min:0', 'max:9007199254740991'],
            'freight_matrices.*.linear_bands' => ['sometimes', 'array', 'max:100'],
            'freight_matrices.*.linear_bands.*' => ['required', 'array:id,from,to,step_kg,cells'],
            'freight_matrices.*.linear_bands.*.id' => ['required', 'string', 'max:64'],
            'freight_matrices.*.linear_bands.*.from' => ['required', 'numeric', 'min:0', 'decimal:0,4'],
            'freight_matrices.*.linear_bands.*.step_kg' => ['required', 'numeric', 'gt:0', 'max:99999999', 'decimal:0,4'],
            'freight_matrices.*.linear_bands.*.cells' => ['required', 'array', 'max:100'],
            'freight_matrices.*.linear_bands.*.cells.*.id' => ['required', 'string', 'max:64'],
            'freight_matrices.*.linear_bands.*.cells.*.zone_id' => ['required', 'integer', 'min:0', 'max:4294967295'],
            'freight_matrices.*.linear_bands.*.cells.*.state' => ['required', 'in:EMPTY,RATE,UNCOVERED'],
            'freight_matrices.*.linear_bands.*.cells.*.amount' => ['nullable', 'integer', 'min:0', 'max:9007199254740991'],
            'freight_matrices.*.linear_bands.*.to' => ['present', 'nullable', 'numeric', 'gt:0', 'decimal:0,4'],
            'rules.*.matrix_cell_id' => ['nullable', 'string', 'max:64'],
            'rules.*.taxable' => ['nullable', 'boolean'],
        ];
    }
}
