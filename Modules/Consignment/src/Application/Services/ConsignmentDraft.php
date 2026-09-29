<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Services;

use Modules\Consignment\Application\Contracts\ConsignmentDraftInterface;
use Modules\Consignment\Application\Contracts\ConsignmentTimeInterface;
use Modules\Consignment\Application\Dto\ConsignmentContactDto;
use Modules\Consignment\Application\Dto\ConsignmentDraftDto;
use Modules\Consignment\Application\Dto\ConsignmentParcelDto;
use Modules\Consignment\Application\Dto\ConsignmentPricingOptionDto;
use Modules\Consignment\Application\Mappers\ConsignmentInputMapper;
use Modules\Consignment\Application\Serialization\ConsignmentDraftDocument;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Geography\Application\Contracts\GeographyResolverInterface;

final readonly class ConsignmentDraft implements ConsignmentDraftInterface
{
    public function __construct(
        private ConsignmentTimeInterface $consignmentTime,
        private GeographyResolverInterface $geographyResolver,
    ) {}

    public function draftFromRow(ConsignmentRecord $row): ConsignmentDraftDto
    {
        return ConsignmentInputMapper::draft([
            'sender' => ConsignmentDraftDocument::contact($this->contactFromRow('sender', $row)),
            'receiver' => ConsignmentDraftDocument::contact($this->contactFromRow('receiver', $row)),
            'service_type_id' => $row->service_type_id,
            'shipping_method_id' => $row->shipping_method_id,
            'service_offering_id' => $row->service_offering_id,
            'service_offering_version_id' => $row->service_offering_version_id,
            'selected_option_version_ids' => $row->selected_service_option_versions ?? [],
            'commitment_schedule_version_id' => $row->commitment_schedule_version_id,
            'pickup_service_date' => $row->pickup_service_date,
            'pickup_window_code' => $row->pickup_window_code,
            'delivery_window_code' => $row->delivery_window_code,
            'pickup_commitment_at' => $row->pickup_commitment_at ? $this->consignmentTime->time($row->pickup_commitment_at) : null,
            'pickup_commitment_start_at' => $row->pickup_commitment_start_at ? $this->consignmentTime->time($row->pickup_commitment_start_at) : null,
            'pickup_commitment_end_at' => $row->pickup_commitment_end_at ? $this->consignmentTime->time($row->pickup_commitment_end_at) : null,
            'delivery_commitment_at' => $row->delivery_commitment_at ? $this->consignmentTime->time($row->delivery_commitment_at) : null,
            'delivery_commitment_start_at' => $row->delivery_commitment_start_at ? $this->consignmentTime->time($row->delivery_commitment_start_at) : null,
            'delivery_commitment_end_at' => $row->delivery_commitment_end_at ? $this->consignmentTime->time($row->delivery_commitment_end_at) : null,
            'delivery_commitment_resolution' => $row->delivery_commitment_resolution,
            'commitment_snapshot' => $row->commitment_snapshot,
            'weight_kg' => (float) $row->weight_kg,
            'width_cm' => $row->width_cm === null ? null : (float) $row->width_cm,
            'length_cm' => $row->length_cm === null ? null : (float) $row->length_cm,
            'height_cm' => $row->height_cm === null ? null : (float) $row->height_cm,
            'declared_value_amount' => (int) $row->declared_value_amount,
            'insurance_enabled' => (bool) $row->insurance_enabled,
            'insurance_value_amount' => $row->insurance_value_amount === null ? null : (int) $row->insurance_value_amount,
            'cod_enabled' => (bool) $row->cod_enabled,
            'cod_amount' => $row->cod_amount === null ? null : (int) $row->cod_amount,
            'payer' => $row->payer,
            'payment_method' => $row->payment_method,
            'parcels' => array_map(fn ($parcel): array => [
                'weight_kg' => $parcel->weight_kg === null ? null : (float) $parcel->weight_kg,
                'content_description' => $parcel->content_description,
                'width_cm' => $parcel->width_cm === null ? null : (float) $parcel->width_cm,
                'length_cm' => $parcel->length_cm === null ? null : (float) $parcel->length_cm,
                'height_cm' => $parcel->height_cm === null ? null : (float) $parcel->height_cm,
            ], $row->parcels->all()),
        ]);
    }

    public function commercialColumns(ConsignmentDraftDto $input): array
    {
        return [
            'service_type_id' => $input->serviceTypeId,
            'shipping_method_id' => $input->shippingMethodId,
            'service_offering_id' => $input->serviceOfferingId ?? null,
            'service_offering_version_id' => $input->serviceOfferingVersionId ?? null,
            'selected_service_option_versions' => isset($input->selectedOptionVersionIds) ? array_values($input->selectedOptionVersionIds) : null,
            'commitment_schedule_version_id' => $input->commitmentScheduleVersionId ?? null,
            'pickup_service_date' => $input->pickupServiceDate ?? null,
            'pickup_window_code' => $input->pickupWindowCode ?? null,
            'delivery_window_code' => $input->deliveryWindowCode ?? null,
            'pickup_commitment_at' => $this->consignmentTime->databaseTime($input->pickupCommitmentAt ?? null),
            'pickup_commitment_start_at' => $this->consignmentTime->databaseTime($input->pickupCommitmentStartAt ?? null),
            'pickup_commitment_end_at' => $this->consignmentTime->databaseTime($input->pickupCommitmentEndAt ?? null),
            'delivery_commitment_at' => $this->consignmentTime->databaseTime($input->deliveryCommitmentAt ?? null),
            'delivery_commitment_start_at' => $this->consignmentTime->databaseTime($input->deliveryCommitmentStartAt ?? null),
            'delivery_commitment_end_at' => $this->consignmentTime->databaseTime($input->deliveryCommitmentEndAt ?? null),
            'commitment_snapshot' => $input->commitmentSnapshot ?? null,
            'weight_kg' => $input->weightKg,
            'width_cm' => $input->widthCm ?? null,
            'length_cm' => $input->lengthCm ?? null,
            'height_cm' => $input->heightCm ?? null,
            'declared_value_amount' => $input->declaredValueAmount,
            'insurance_enabled' => $input->insuranceEnabled,
            'insurance_value_amount' => $input->insuranceValueAmount ?? null,
            'cod_enabled' => $input->codEnabled,
            'cod_amount' => $input->codAmount ?? null,
            'payer' => $input->payer,
            'payment_method' => $input->paymentMethod,
        ];
    }

    public function contactColumns(string $prefix, ConsignmentContactDto $contact): array
    {
        $values = ConsignmentDraftDocument::contact($contact);
        $result = [];
        foreach ([
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
        ] as $field) {
            $result["{$prefix}_{$field}"] = $values[$field] ?? null;
        }

        return $result;
    }

    public function contactFromRow(string $prefix, ConsignmentRecord $row): ConsignmentContactDto
    {
        $result = ['address_book_entry_id' => null];
        foreach ([
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
        ] as $field) {
            $value = $row["{$prefix}_{$field}"];
            $result[$field] = in_array($field, ['latitude', 'longitude'], true) && $value !== null ? (float) $value : $value;
        }
        $result['city_reference'] = $this->geographyResolver->cityReference(isset($row["{$prefix}_city_id"]) ? (string) $row["{$prefix}_city_id"] : null);

        return ConsignmentInputMapper::contact($result);
    }

    public function parcelPhysical(ConsignmentParcelDto $parcel, ConsignmentDraftDto $aggregate): array
    {
        return [
            'weight_kg' => $parcel->weightKg ?? $aggregate->weightKg,
            'width_cm' => $parcel->widthCm ?? $aggregate->widthCm,
            'length_cm' => $parcel->lengthCm ?? $aggregate->lengthCm,
            'height_cm' => $parcel->heightCm ?? $aggregate->heightCm,
        ];
    }

    public function withAcceptedCommitment(ConsignmentDraftDto $input, ?array $commitment): ConsignmentDraftDto
    {
        if ($commitment === null) {
            return $input;
        }
        $input = clone $input;
        $pickup = is_array($commitment['pickup'] ?? null) ? $commitment['pickup'] : [];
        $delivery = is_array($commitment['delivery'] ?? null) ? $commitment['delivery'] : [];
        $selectedDelivery = is_array($delivery['selected'] ?? null) ? $delivery['selected'] : [];
        $computedDelivery = $delivery['computed_at'] ?? null;
        $input->commitmentScheduleVersionId = $commitment['schedule_version_id'] ?? null;
        $input->pickupServiceDate = $pickup['service_date'] ?? null;
        $input->pickupWindowCode = $pickup['window_code'] ?? null;
        $input->deliveryWindowCode = $selectedDelivery['window_code'] ?? null;
        $input->pickupCommitmentStartAt = $pickup['starts_at'] ?? null;
        $input->pickupCommitmentEndAt = $pickup['ends_at'] ?? null;
        $input->pickupCommitmentAt = $pickup['ends_at'] ?? $pickup['computed_at'] ?? null;
        $input->deliveryCommitmentStartAt = $selectedDelivery['starts_at'] ?? $computedDelivery;
        $input->deliveryCommitmentEndAt = $selectedDelivery['ends_at'] ?? $computedDelivery;
        $input->deliveryCommitmentAt = $selectedDelivery['ends_at'] ?? $computedDelivery;
        $input->commitmentSnapshot = $commitment;

        return $input;
    }

    public function withAcceptedOffering(ConsignmentDraftDto $draft, ConsignmentPricingOptionDto $option): ConsignmentDraftDto
    {
        $draft = clone $draft;
        $draft->serviceTypeId = $option->serviceTypeId;
        $draft->shippingMethodId = $option->shippingMethodId;
        $draft->serviceOfferingId = $option->serviceOfferingId;
        $draft->serviceOfferingVersionId = $option->serviceOfferingVersionId;
        $draft->selectedOptionVersionIds = $option->selectedOptionVersionIds;

        return $this->withAcceptedCommitment($draft, $option->commitment);
    }
}
