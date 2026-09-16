<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Services;

use Carbon\CarbonImmutable;

final readonly class ConsignmentQuoteInput
{
    public function __construct(private \Modules\Geography\Application\GeographyResolver $geography)
    {
    }

    public function normalized(array $input): array
    {
        // These are server-resolved evidence, not editable quote inputs. The new
        // resolved commitment is fingerprinted separately with the provider result.
        foreach ([
            'commitment_schedule_version_id',
            'pickup_commitment_start_at',
            'pickup_commitment_end_at',
            'delivery_commitment_start_at',
            'delivery_commitment_end_at',
            'commitment_snapshot',
            'delivery_commitment_resolution',
        ] as $field) {
            $input[$field] = null;
        }
        foreach (['sender', 'receiver'] as $party) {
            $contact = $this->geography->canonicalizeContact((array) ($input[$party] ?? []), true);
            unset($contact['city_reference']);
            foreach (['address_book_entry_id', 'phone', 'country', 'postal_code', 'latitude', 'longitude'] as $field) {
                $contact[$field] ??= null;
            }
            $input[$party] = $contact;
        }
        foreach ([
            'pickup_commitment_at',
            'delivery_commitment_at',
            'width_cm',
            'length_cm',
            'height_cm',
            'insurance_value_amount',
            'cod_amount',
            'service_offering_id',
            'service_offering_version_id',
            'pickup_service_date',
            'pickup_window_code',
            'delivery_window_code',
            'commitment_schedule_version_id',
            'pickup_commitment_start_at',
            'pickup_commitment_end_at',
            'delivery_commitment_start_at',
            'delivery_commitment_end_at',
            'commitment_snapshot',
            'delivery_commitment_resolution',
        ] as $field) {
            $input[$field] ??= null;
        }
        $input['selected_option_version_ids'] = array_values((array) ($input['selected_option_version_ids'] ?? []));
        foreach (['pickup_commitment_at', 'delivery_commitment_at'] as $field) {
            if ($input[$field] !== null) {
                $input[$field] = CarbonImmutable::parse((string) $input[$field])->utc()->toISOString();
            }
        }
        $input['parcels'] = array_map(static function (array $parcel): array {
            foreach (['weight_kg', 'width_cm', 'length_cm', 'height_cm'] as $field) {
                $parcel[$field] ??= null;
            }
            $parcel['content_description'] = trim((string) ($parcel['content_description'] ?? ''));
            return $parcel;
        }, (array) ($input['parcels'] ?? []));
        return $input;
    }
}
