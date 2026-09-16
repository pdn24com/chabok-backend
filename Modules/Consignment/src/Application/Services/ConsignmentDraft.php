<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Services;

final readonly class ConsignmentDraft
{
    public function __construct(
        private \Modules\Consignment\Application\Services\ConsignmentTime $consignmentTime,
        private \Modules\Consignment\Application\Repositories\ConsignmentRepository $consignments,
        private \Modules\Geography\Application\GeographyResolver $geography,
    )
    {
    }

    public function draftFromRow(array $row): array
    {
        return [
            'sender' => $this->contactFromRow('sender', $row),
            'receiver' => $this->contactFromRow('receiver', $row),
            'service_type_id' => $row['service_type_id'],
            'shipping_method_id' => $row['shipping_method_id'],
            'service_offering_id' => $row['service_offering_id'],
            'service_offering_version_id' => $row['service_offering_version_id'],
            'selected_option_version_ids' => $row['selected_service_option_versions'] ? json_decode((string) $row['selected_service_option_versions'], true) : [],
            'commitment_schedule_version_id' => $row['commitment_schedule_version_id'],
            'pickup_service_date' => $row['pickup_service_date'],
            'pickup_window_code' => $row['pickup_window_code'],
            'delivery_window_code' => $row['delivery_window_code'],
            'pickup_commitment_at' => $row['pickup_commitment_at'] ? $this->consignmentTime->time($row['pickup_commitment_at']) : null,
            'pickup_commitment_start_at' => $row['pickup_commitment_start_at'] ? $this->consignmentTime->time($row['pickup_commitment_start_at']) : null,
            'pickup_commitment_end_at' => $row['pickup_commitment_end_at'] ? $this->consignmentTime->time($row['pickup_commitment_end_at']) : null,
            'delivery_commitment_at' => $row['delivery_commitment_at'] ? $this->consignmentTime->time($row['delivery_commitment_at']) : null,
            'delivery_commitment_start_at' => $row['delivery_commitment_start_at'] ? $this->consignmentTime->time($row['delivery_commitment_start_at']) : null,
            'delivery_commitment_end_at' => $row['delivery_commitment_end_at'] ? $this->consignmentTime->time($row['delivery_commitment_end_at']) : null,
            'delivery_commitment_resolution' => empty($row['delivery_commitment_resolution']) ? null : json_decode((string) $row['delivery_commitment_resolution'], true),
            'commitment_snapshot' => $row['commitment_snapshot'] ? json_decode((string) $row['commitment_snapshot'], true) : null,
            'weight_kg' => (float) $row['weight_kg'],
            'width_cm' => $row['width_cm'] === null ? null : (float) $row['width_cm'],
            'length_cm' => $row['length_cm'] === null ? null : (float) $row['length_cm'],
            'height_cm' => $row['height_cm'] === null ? null : (float) $row['height_cm'],
            'declared_value_amount' => (int) $row['declared_value_amount'],
            'insurance_enabled' => (bool) $row['insurance_enabled'],
            'insurance_value_amount' => $row['insurance_value_amount'] === null ? null : (int) $row['insurance_value_amount'],
            'cod_enabled' => (bool) $row['cod_enabled'],
            'cod_amount' => $row['cod_amount'] === null ? null : (int) $row['cod_amount'],
            'payer' => $row['payer'],
            'payment_method' => $row['payment_method'],
            'parcels' => array_map(fn($parcel): array => [
                'weight_kg' => $parcel->weight_kg === null ? null : (float) $parcel->weight_kg,
                'content_description' => $parcel->content_description,
                'width_cm' => $parcel->width_cm === null ? null : (float) $parcel->width_cm,
                'length_cm' => $parcel->length_cm === null ? null : (float) $parcel->length_cm,
                'height_cm' => $parcel->height_cm === null ? null : (float) $parcel->height_cm,
            ], $this->consignments->draftParcels($row['hq_id'], $row['consignment_id'])),
        ];
    }

    public function commercialColumns(array $input): array
    {
        return [
            'service_type_id' => $input['service_type_id'],
            'shipping_method_id' => $input['shipping_method_id'],
            'service_offering_id' => $input['service_offering_id'] ?? null,
            'service_offering_version_id' => $input['service_offering_version_id'] ?? null,
            'selected_service_option_versions' => isset($input['selected_option_version_ids']) ? json_encode(array_values((array) $input['selected_option_version_ids']), JSON_THROW_ON_ERROR) : null,
            'commitment_schedule_version_id' => $input['commitment_schedule_version_id'] ?? null,
            'pickup_service_date' => $input['pickup_service_date'] ?? null,
            'pickup_window_code' => $input['pickup_window_code'] ?? null,
            'delivery_window_code' => $input['delivery_window_code'] ?? null,
            'pickup_commitment_at' => $this->consignmentTime->databaseTime($input['pickup_commitment_at'] ?? null),
            'pickup_commitment_start_at' => $this->consignmentTime->databaseTime($input['pickup_commitment_start_at'] ?? null),
            'pickup_commitment_end_at' => $this->consignmentTime->databaseTime($input['pickup_commitment_end_at'] ?? null),
            'delivery_commitment_at' => $this->consignmentTime->databaseTime($input['delivery_commitment_at'] ?? null),
            'delivery_commitment_start_at' => $this->consignmentTime->databaseTime($input['delivery_commitment_start_at'] ?? null),
            'delivery_commitment_end_at' => $this->consignmentTime->databaseTime($input['delivery_commitment_end_at'] ?? null),
            'commitment_snapshot' => isset($input['commitment_snapshot']) ? json_encode($input['commitment_snapshot'], JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE) : null,
            'weight_kg' => $input['weight_kg'],
            'width_cm' => $input['width_cm'] ?? null,
            'length_cm' => $input['length_cm'] ?? null,
            'height_cm' => $input['height_cm'] ?? null,
            'declared_value_amount' => $input['declared_value_amount'],
            'insurance_enabled' => $input['insurance_enabled'],
            'insurance_value_amount' => $input['insurance_value_amount'] ?? null,
            'cod_enabled' => $input['cod_enabled'],
            'cod_amount' => $input['cod_amount'] ?? null,
            'payer' => $input['payer'],
            'payment_method' => $input['payment_method'],
        ];
    }

    public function contactColumns(string $prefix, array $contact): array
    {
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
            $result["{$prefix}_{$field}"] = $contact[$field] ?? null;
        }
        return $result;
    }

    public function contactFromRow(string $prefix, array $row): array
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
        $result['city_reference'] = $this->geography->cityReference(isset($row["{$prefix}_city_id"]) ? (string) $row["{$prefix}_city_id"] : null);
        return $result;
    }

    public function parcelPhysical(array $parcel, array $aggregate): array
    {
        $result = [];
        foreach (['weight_kg', 'width_cm', 'length_cm', 'height_cm'] as $field) {
            $result[$field] = $parcel[$field] ?? $aggregate[$field] ?? null;
        }
        return $result;
    }

    public function withAcceptedCommitment(array $input, ?array $commitment): array
    {
        if ($commitment === null) {
            return $input;
        }
        $pickup = is_array($commitment['pickup'] ?? null) ? $commitment['pickup'] : [];
        $delivery = is_array($commitment['delivery'] ?? null) ? $commitment['delivery'] : [];
        $selectedDelivery = is_array($delivery['selected'] ?? null) ? $delivery['selected'] : [];
        $computedDelivery = $delivery['computed_at'] ?? null;
        $input['commitment_schedule_version_id'] = $commitment['schedule_version_id'] ?? null;
        $input['pickup_service_date'] = $pickup['service_date'] ?? null;
        $input['pickup_window_code'] = $pickup['window_code'] ?? null;
        $input['delivery_window_code'] = $selectedDelivery['window_code'] ?? null;
        $input['pickup_commitment_start_at'] = $pickup['starts_at'] ?? null;
        $input['pickup_commitment_end_at'] = $pickup['ends_at'] ?? null;
        $input['pickup_commitment_at'] = $pickup['ends_at'] ?? $pickup['computed_at'] ?? null;
        $input['delivery_commitment_start_at'] = $selectedDelivery['starts_at'] ?? $computedDelivery;
        $input['delivery_commitment_end_at'] = $selectedDelivery['ends_at'] ?? $computedDelivery;
        $input['delivery_commitment_at'] = $selectedDelivery['ends_at'] ?? $computedDelivery;
        $input['commitment_snapshot'] = $commitment;
        return $input;
    }
}
