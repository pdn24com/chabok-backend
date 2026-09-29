<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Serialization;

use Modules\Pricing\Application\Dto\QuoteContactDto;
use Modules\Pricing\Application\Dto\QuoteInputDto;
use Modules\Pricing\Application\Dto\QuoteParcelDto;

final class QuoteInputDocument
{
    /** @return array<string, mixed> */
    public static function quote(QuoteInputDto $data): array
    {
        $fields = [
            'purpose' => $data->purpose,
            'channel' => $data->channel,
            'as_of_timestamp' => $data->asOfTimestamp,
            'acceptance_at' => $data->acceptanceAt,
            'service_offering_id' => $data->serviceOfferingId,
            'service_offering_version_id' => $data->serviceOfferingVersionId,
            'selected_option_version_ids' => $data->selectedOptionVersionIds,
            'pickup_service_date' => $data->pickupServiceDate,
            'pickup_window_code' => $data->pickupWindowCode,
            'delivery_window_code' => $data->deliveryWindowCode,
            'weight_kg' => $data->weightKg,
            'length_cm' => $data->lengthCm,
            'width_cm' => $data->widthCm,
            'height_cm' => $data->heightCm,
            'declared_value_amount' => $data->declaredValueAmount,
            'insurance_enabled' => $data->insuranceEnabled,
            'cod_enabled' => $data->codEnabled,
            'cod_amount' => $data->codAmount,
            'sender' => self::contact($data->sender),
            'receiver' => self::contact($data->receiver),
            'parcels' => array_map(self::parcel(...), $data->parcels),
        ];

        return [...$data->extensions, ...array_intersect_key($fields, array_flip($data->presentFields))];
    }

    /** @return array<string, mixed> */
    public static function contact(QuoteContactDto $data): array
    {
        $fields = [
            'contact_name' => $data->contactName,
            'mobile' => $data->mobile,
            'phone' => $data->phone,
            'address_text' => $data->addressText,
            'address_book_entry_id' => $data->addressBookEntryId,
            'country' => $data->country,
            'state' => $data->state,
            'city' => $data->city,
            'city_id' => $data->cityId,
            'province_id' => $data->provinceId,
            'postal_code' => $data->postalCode,
            'latitude' => $data->latitude,
            'longitude' => $data->longitude,
        ];

        return [...$data->extensions, ...array_intersect_key($fields, array_flip($data->presentFields))];
    }

    /** @return array<string, mixed> */
    public static function parcel(QuoteParcelDto $data): array
    {
        $fields = [
            'content_description' => $data->contentDescription,
            'weight_kg' => $data->weightKg,
            'length_cm' => $data->lengthCm,
            'width_cm' => $data->widthCm,
            'height_cm' => $data->heightCm,
        ];

        return [...$data->extensions, ...array_intersect_key($fields, array_flip($data->presentFields))];
    }
}
