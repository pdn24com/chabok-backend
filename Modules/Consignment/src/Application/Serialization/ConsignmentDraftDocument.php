<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Serialization;

use Modules\Consignment\Application\Dto\ConsignmentContactDto;
use Modules\Consignment\Application\Dto\ConsignmentDraftDto;
use Modules\Consignment\Application\Dto\ConsignmentParcelDto;

/** Maps typed data only at request, fingerprint, provider, or persistence boundaries. */
final class ConsignmentDraftDocument
{
    public static function contact(ConsignmentContactDto $input): array
    {
        $values = [
            'address_book_entry_id' => $input->addressBookEntryId,
            'contact_name' => $input->contactName,
            'mobile' => $input->mobile,
            'phone' => $input->phone,
            'address_text' => $input->addressText,
            'country' => $input->country,
            'state' => $input->state,
            'city' => $input->city,
            'city_id' => $input->cityId,
            'province_id' => $input->provinceId,
            'legacy_city_code' => $input->legacyCityCode,
            'postal_code' => $input->postalCode,
            'latitude' => $input->latitude,
            'longitude' => $input->longitude,
            'city_reference' => $input->cityReference,
        ];

        return array_intersect_key($values, array_fill_keys($input->presentFields, true));
    }

    public static function parcel(ConsignmentParcelDto $input): array
    {
        $values = [
            'parcel_id' => $input->parcelId,
            'content_description' => $input->contentDescription,
            'weight_kg' => $input->weightKg,
            'width_cm' => $input->widthCm,
            'length_cm' => $input->lengthCm,
            'height_cm' => $input->heightCm,
        ];

        return array_intersect_key($values, array_fill_keys($input->presentFields, true));
    }

    public static function draft(ConsignmentDraftDto $input): array
    {
        $values = [
            'sender' => self::contact($input->sender),
            'receiver' => self::contact($input->receiver),
            'delivery_node_id' => $input->deliveryNodeId,
            'service_type_id' => $input->serviceTypeId,
            'shipping_method_id' => $input->shippingMethodId,
            'service_offering_id' => $input->serviceOfferingId,
            'service_offering_version_id' => $input->serviceOfferingVersionId,
            'commitment_schedule_version_id' => $input->commitmentScheduleVersionId,
            'pickup_service_date' => $input->pickupServiceDate,
            'pickup_window_code' => $input->pickupWindowCode,
            'delivery_window_code' => $input->deliveryWindowCode,
            'pickup_commitment_at' => $input->pickupCommitmentAt,
            'pickup_commitment_start_at' => $input->pickupCommitmentStartAt,
            'pickup_commitment_end_at' => $input->pickupCommitmentEndAt,
            'delivery_commitment_at' => $input->deliveryCommitmentAt,
            'delivery_commitment_start_at' => $input->deliveryCommitmentStartAt,
            'delivery_commitment_end_at' => $input->deliveryCommitmentEndAt,
            'selected_option_version_ids' => $input->selectedOptionVersionIds,
            'weight_kg' => $input->weightKg,
            'width_cm' => $input->widthCm,
            'length_cm' => $input->lengthCm,
            'height_cm' => $input->heightCm,
            'declared_value_amount' => $input->declaredValueAmount,
            'insurance_value_amount' => $input->insuranceValueAmount,
            'cod_amount' => $input->codAmount,
            'insurance_enabled' => $input->insuranceEnabled,
            'cod_enabled' => $input->codEnabled,
            'payer' => $input->payer,
            'payment_method' => $input->paymentMethod,
            'commitment_snapshot' => $input->commitmentSnapshot,
            'delivery_commitment_resolution' => $input->deliveryCommitmentResolution,
            'parcels' => array_map(self::parcel(...), $input->parcels),
        ];

        return array_intersect_key($values, array_fill_keys($input->presentFields, true));
    }
}
