<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Mappers;

use Modules\Consignment\Application\Dto\AcceptedQuoteReferenceDto;
use Modules\Consignment\Application\Dto\ConsignmentContactDto;
use Modules\Consignment\Application\Dto\ConsignmentCreationDto;
use Modules\Consignment\Application\Dto\ConsignmentDraftDto;
use Modules\Consignment\Application\Dto\ConsignmentEditDto;
use Modules\Consignment\Application\Dto\ConsignmentParcelDto;

final class ConsignmentInputMapper
{
    public static function contact(array $input): ConsignmentContactDto
    {
        return new ConsignmentContactDto(
            addressBookEntryId: $input['address_book_entry_id'] ?? null,
            contactName: $input['contact_name'] ?? null,
            mobile: $input['mobile'] ?? null,
            phone: $input['phone'] ?? null,
            addressText: $input['address_text'] ?? null,
            country: $input['country'] ?? null,
            state: $input['state'] ?? null,
            city: $input['city'] ?? null,
            cityId: $input['city_id'] ?? null,
            provinceId: $input['province_id'] ?? null,
            legacyCityCode: $input['legacy_city_code'] ?? null,
            postalCode: $input['postal_code'] ?? null,
            latitude: $input['latitude'] ?? null,
            longitude: $input['longitude'] ?? null,
            cityReference: $input['city_reference'] ?? null,
            presentFields: array_keys($input),
        );
    }

    public static function parcel(array $input): ConsignmentParcelDto
    {
        return new ConsignmentParcelDto(
            parcelId: $input['parcel_id'] ?? null,
            contentDescription: $input['content_description'] ?? null,
            weightKg: $input['weight_kg'] ?? null,
            widthCm: $input['width_cm'] ?? null,
            lengthCm: $input['length_cm'] ?? null,
            heightCm: $input['height_cm'] ?? null,
            presentFields: array_keys($input),
        );
    }

    public static function draft(array $input): ConsignmentDraftDto
    {
        return new ConsignmentDraftDto(
            sender: self::contact($input['sender'] ?? []),
            receiver: self::contact($input['receiver'] ?? []),
            deliveryNodeId: $input['delivery_node_id'] ?? null,
            serviceTypeId: $input['service_type_id'] ?? null,
            shippingMethodId: $input['shipping_method_id'] ?? null,
            serviceOfferingId: $input['service_offering_id'] ?? null,
            serviceOfferingVersionId: $input['service_offering_version_id'] ?? null,
            commitmentScheduleVersionId: $input['commitment_schedule_version_id'] ?? null,
            pickupServiceDate: $input['pickup_service_date'] ?? null,
            pickupWindowCode: $input['pickup_window_code'] ?? null,
            deliveryWindowCode: $input['delivery_window_code'] ?? null,
            pickupCommitmentAt: $input['pickup_commitment_at'] ?? null,
            pickupCommitmentStartAt: $input['pickup_commitment_start_at'] ?? null,
            pickupCommitmentEndAt: $input['pickup_commitment_end_at'] ?? null,
            deliveryCommitmentAt: $input['delivery_commitment_at'] ?? null,
            deliveryCommitmentStartAt: $input['delivery_commitment_start_at'] ?? null,
            deliveryCommitmentEndAt: $input['delivery_commitment_end_at'] ?? null,
            selectedOptionVersionIds: $input['selected_option_version_ids'] ?? [],
            weightKg: $input['weight_kg'] ?? null,
            widthCm: $input['width_cm'] ?? null,
            lengthCm: $input['length_cm'] ?? null,
            heightCm: $input['height_cm'] ?? null,
            declaredValueAmount: $input['declared_value_amount'] ?? null,
            insuranceValueAmount: $input['insurance_value_amount'] ?? null,
            codAmount: $input['cod_amount'] ?? null,
            insuranceEnabled: $input['insurance_enabled'] ?? false,
            codEnabled: $input['cod_enabled'] ?? false,
            payer: $input['payer'] ?? null,
            paymentMethod: $input['payment_method'] ?? null,
            commitmentSnapshot: $input['commitment_snapshot'] ?? null,
            deliveryCommitmentResolution: $input['delivery_commitment_resolution'] ?? null,
            parcels: array_map(self::parcel(...), $input['parcels'] ?? []),
            presentFields: array_keys($input),
        );
    }

    public static function reference(array $input): AcceptedQuoteReferenceDto
    {
        return new AcceptedQuoteReferenceDto((string) ($input['quote_id'] ?? ''), (int) ($input['quote_version'] ?? 0), (string) ($input['option_id'] ?? ''));
    }

    public static function creation(array $input): ConsignmentCreationDto
    {
        return new ConsignmentCreationDto(self::draft($input), self::reference($input['accepted_quote']));
    }

    public static function edit(array $input): ConsignmentEditDto
    {
        $reference = isset($input['accepted_quote']) ? self::reference($input['accepted_quote']) : null;
        $version = (int) $input['expected_version'];
        $reason = (string) $input['change_reason'];
        $note = $input['note'] ?? null;
        unset($input['accepted_quote'], $input['expected_version'], $input['change_reason'], $input['note']);

        return new ConsignmentEditDto($version, $reason, $note, self::draft($input), $reference);
    }
}
