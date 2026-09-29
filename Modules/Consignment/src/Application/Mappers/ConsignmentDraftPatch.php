<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Mappers;

use Modules\Consignment\Application\Dto\ConsignmentContactDto;
use Modules\Consignment\Application\Dto\ConsignmentDraftDto;
use Modules\Consignment\Application\Dto\ConsignmentParcelDto;

final class ConsignmentDraftPatch
{
    public static function apply(ConsignmentDraftDto $before, ConsignmentDraftDto $changes): ConsignmentDraftDto
    {
        $result = clone $before;
        $result->sender = self::contact($before->sender, $changes->sender);
        $result->receiver = self::contact($before->receiver, $changes->receiver);
        if (in_array('delivery_node_id', $changes->presentFields, true)) {
            $result->deliveryNodeId = $changes->deliveryNodeId;
        }
        if (in_array('service_type_id', $changes->presentFields, true)) {
            $result->serviceTypeId = $changes->serviceTypeId;
        }
        if (in_array('shipping_method_id', $changes->presentFields, true)) {
            $result->shippingMethodId = $changes->shippingMethodId;
        }
        if (in_array('service_offering_id', $changes->presentFields, true)) {
            $result->serviceOfferingId = $changes->serviceOfferingId;
        }
        if (in_array('service_offering_version_id', $changes->presentFields, true)) {
            $result->serviceOfferingVersionId = $changes->serviceOfferingVersionId;
        }
        if (in_array('commitment_schedule_version_id', $changes->presentFields, true)) {
            $result->commitmentScheduleVersionId = $changes->commitmentScheduleVersionId;
        }
        if (in_array('pickup_service_date', $changes->presentFields, true)) {
            $result->pickupServiceDate = $changes->pickupServiceDate;
        }
        if (in_array('pickup_window_code', $changes->presentFields, true)) {
            $result->pickupWindowCode = $changes->pickupWindowCode;
        }
        if (in_array('delivery_window_code', $changes->presentFields, true)) {
            $result->deliveryWindowCode = $changes->deliveryWindowCode;
        }
        if (in_array('pickup_commitment_at', $changes->presentFields, true)) {
            $result->pickupCommitmentAt = $changes->pickupCommitmentAt;
        }
        if (in_array('pickup_commitment_start_at', $changes->presentFields, true)) {
            $result->pickupCommitmentStartAt = $changes->pickupCommitmentStartAt;
        }
        if (in_array('pickup_commitment_end_at', $changes->presentFields, true)) {
            $result->pickupCommitmentEndAt = $changes->pickupCommitmentEndAt;
        }
        if (in_array('delivery_commitment_at', $changes->presentFields, true)) {
            $result->deliveryCommitmentAt = $changes->deliveryCommitmentAt;
        }
        if (in_array('delivery_commitment_start_at', $changes->presentFields, true)) {
            $result->deliveryCommitmentStartAt = $changes->deliveryCommitmentStartAt;
        }
        if (in_array('delivery_commitment_end_at', $changes->presentFields, true)) {
            $result->deliveryCommitmentEndAt = $changes->deliveryCommitmentEndAt;
        }
        if (in_array('selected_option_version_ids', $changes->presentFields, true)) {
            $result->selectedOptionVersionIds = array_replace($before->selectedOptionVersionIds, $changes->selectedOptionVersionIds);
        }
        if (in_array('weight_kg', $changes->presentFields, true)) {
            $result->weightKg = $changes->weightKg;
        }
        if (in_array('width_cm', $changes->presentFields, true)) {
            $result->widthCm = $changes->widthCm;
        }
        if (in_array('length_cm', $changes->presentFields, true)) {
            $result->lengthCm = $changes->lengthCm;
        }
        if (in_array('height_cm', $changes->presentFields, true)) {
            $result->heightCm = $changes->heightCm;
        }
        if (in_array('declared_value_amount', $changes->presentFields, true)) {
            $result->declaredValueAmount = $changes->declaredValueAmount;
        }
        if (in_array('insurance_value_amount', $changes->presentFields, true)) {
            $result->insuranceValueAmount = $changes->insuranceValueAmount;
        }
        if (in_array('cod_amount', $changes->presentFields, true)) {
            $result->codAmount = $changes->codAmount;
        }
        if (in_array('insurance_enabled', $changes->presentFields, true)) {
            $result->insuranceEnabled = $changes->insuranceEnabled;
        }
        if (in_array('cod_enabled', $changes->presentFields, true)) {
            $result->codEnabled = $changes->codEnabled;
        }
        if (in_array('payer', $changes->presentFields, true)) {
            $result->payer = $changes->payer;
        }
        if (in_array('payment_method', $changes->presentFields, true)) {
            $result->paymentMethod = $changes->paymentMethod;
        }
        if (in_array('commitment_snapshot', $changes->presentFields, true)) {
            $result->commitmentSnapshot = $changes->commitmentSnapshot;
        }
        if (in_array('delivery_commitment_resolution', $changes->presentFields, true)) {
            $result->deliveryCommitmentResolution = $changes->deliveryCommitmentResolution;
        }
        if (in_array('parcels', $changes->presentFields, true)) {
            $result->parcels = [];
            foreach ($changes->parcels as $index => $parcel) {
                $result->parcels[] = self::parcel($before->parcels[$index], $parcel);
            }
        }
        $result->presentFields = array_values(array_unique([...$before->presentFields, ...$changes->presentFields]));

        return $result;
    }

    private static function contact(ConsignmentContactDto $before, ConsignmentContactDto $changes): ConsignmentContactDto
    {
        $result = clone $before;
        if (in_array('address_book_entry_id', $changes->presentFields, true)) {
            $result->addressBookEntryId = $changes->addressBookEntryId;
        }
        if (in_array('contact_name', $changes->presentFields, true)) {
            $result->contactName = $changes->contactName;
        }
        if (in_array('mobile', $changes->presentFields, true)) {
            $result->mobile = $changes->mobile;
        }
        if (in_array('phone', $changes->presentFields, true)) {
            $result->phone = $changes->phone;
        }
        if (in_array('address_text', $changes->presentFields, true)) {
            $result->addressText = $changes->addressText;
        }
        if (in_array('country', $changes->presentFields, true)) {
            $result->country = $changes->country;
        }
        if (in_array('state', $changes->presentFields, true)) {
            $result->state = $changes->state;
        }
        if (in_array('city', $changes->presentFields, true)) {
            $result->city = $changes->city;
        }
        if (in_array('city_id', $changes->presentFields, true)) {
            $result->cityId = $changes->cityId;
        }
        if (in_array('province_id', $changes->presentFields, true)) {
            $result->provinceId = $changes->provinceId;
        }
        if (in_array('legacy_city_code', $changes->presentFields, true)) {
            $result->legacyCityCode = $changes->legacyCityCode;
        }
        if (in_array('postal_code', $changes->presentFields, true)) {
            $result->postalCode = $changes->postalCode;
        }
        if (in_array('latitude', $changes->presentFields, true)) {
            $result->latitude = $changes->latitude;
        }
        if (in_array('longitude', $changes->presentFields, true)) {
            $result->longitude = $changes->longitude;
        }
        if (in_array('city_reference', $changes->presentFields, true)) {
            $result->cityReference = $changes->cityReference;
        }
        $result->presentFields = array_values(array_unique([...$before->presentFields, ...$changes->presentFields]));

        return $result;
    }

    private static function parcel(ConsignmentParcelDto $before, ConsignmentParcelDto $changes): ConsignmentParcelDto
    {
        $result = clone $before;
        if (in_array('parcel_id', $changes->presentFields, true)) {
            $result->parcelId = $changes->parcelId;
        }
        if (in_array('content_description', $changes->presentFields, true)) {
            $result->contentDescription = $changes->contentDescription;
        }
        if (in_array('weight_kg', $changes->presentFields, true)) {
            $result->weightKg = $changes->weightKg;
        }
        if (in_array('width_cm', $changes->presentFields, true)) {
            $result->widthCm = $changes->widthCm;
        }
        if (in_array('length_cm', $changes->presentFields, true)) {
            $result->lengthCm = $changes->lengthCm;
        }
        if (in_array('height_cm', $changes->presentFields, true)) {
            $result->heightCm = $changes->heightCm;
        }
        $result->presentFields = array_values(array_unique([...$before->presentFields, ...$changes->presentFields]));

        return $result;
    }
}
