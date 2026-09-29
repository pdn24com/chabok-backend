<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Mappers;

use Modules\Pricing\Application\Dto\QuoteContactDto;
use Modules\Pricing\Application\Dto\QuoteInputDto;
use Modules\Pricing\Application\Dto\QuoteParcelDto;

final class QuoteInputMapper
{
    public static function quote(array $input): QuoteInputDto
    {
        return new QuoteInputDto(
            sender: QuoteInputMapper::contact((array) ($input['sender'] ?? [])),
            receiver: QuoteInputMapper::contact((array) ($input['receiver'] ?? [])),
            purpose: $input['purpose'] ?? 'SALES',
            channel: $input['channel'] ?? 'BRANCH',
            asOfTimestamp: $input['as_of_timestamp'] ?? null,
            acceptanceAt: $input['acceptance_at'] ?? null,
            serviceOfferingId: $input['service_offering_id'] ?? '',
            serviceOfferingVersionId: $input['service_offering_version_id'] ?? null,
            selectedOptionVersionIds: $input['selected_option_version_ids'] ?? [],
            pickupServiceDate: $input['pickup_service_date'] ?? null,
            pickupWindowCode: $input['pickup_window_code'] ?? null,
            deliveryWindowCode: $input['delivery_window_code'] ?? null,
            weightKg: $input['weight_kg'] ?? null,
            lengthCm: $input['length_cm'] ?? null,
            widthCm: $input['width_cm'] ?? null,
            heightCm: $input['height_cm'] ?? null,
            declaredValueAmount: $input['declared_value_amount'] ?? 0,
            insuranceEnabled: $input['insurance_enabled'] ?? false,
            codEnabled: $input['cod_enabled'] ?? false,
            codAmount: $input['cod_amount'] ?? null,
            parcels: array_map(self::parcel(...), $input['parcels'] ?? []),
            presentFields: array_keys($input),
            extensions: array_diff_key($input, array_flip(['purpose', 'channel', 'as_of_timestamp', 'acceptance_at', 'service_offering_id', 'service_offering_version_id', 'selected_option_version_ids', 'pickup_service_date', 'pickup_window_code', 'delivery_window_code', 'weight_kg', 'length_cm', 'width_cm', 'height_cm', 'declared_value_amount', 'insurance_enabled', 'cod_enabled', 'cod_amount', 'sender', 'receiver', 'parcels'])),
        );
    }

    public static function contact(array $input): QuoteContactDto
    {
        return new QuoteContactDto(
            contactName: $input['contact_name'] ?? null,
            mobile: $input['mobile'] ?? null,
            phone: $input['phone'] ?? null,
            addressText: $input['address_text'] ?? null,
            addressBookEntryId: $input['address_book_entry_id'] ?? null,
            country: $input['country'] ?? null,
            state: $input['state'] ?? null,
            city: $input['city'] ?? null,
            cityId: $input['city_id'] ?? null,
            provinceId: $input['province_id'] ?? null,
            postalCode: $input['postal_code'] ?? null,
            latitude: $input['latitude'] ?? null,
            longitude: $input['longitude'] ?? null,
            presentFields: array_keys($input),
            extensions: array_diff_key($input, array_flip(['contact_name', 'mobile', 'phone', 'address_text', 'address_book_entry_id', 'country', 'state', 'city', 'city_id', 'province_id', 'postal_code', 'latitude', 'longitude'])),
        );
    }

    public static function parcel(array $input): QuoteParcelDto
    {
        return new QuoteParcelDto(
            contentDescription: $input['content_description'] ?? null,
            weightKg: $input['weight_kg'] ?? null,
            lengthCm: $input['length_cm'] ?? null,
            widthCm: $input['width_cm'] ?? null,
            heightCm: $input['height_cm'] ?? null,
            presentFields: array_keys($input),
            extensions: array_diff_key($input, array_flip(['content_description', 'weight_kg', 'length_cm', 'width_cm', 'height_cm'])),
        );
    }
}
