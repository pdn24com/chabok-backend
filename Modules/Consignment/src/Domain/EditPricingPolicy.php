<?php

declare(strict_types=1);

namespace Modules\Consignment\Domain;

final class EditPricingPolicy
{
    public function changed(array $before, array $after, array $safeContactFields): bool
    {
        foreach (['sender', 'receiver'] as $party) {
            // Geography canonicalization enriches contacts with derived metadata.
            foreach (['city_reference', 'province_id', 'legacy_city_code'] as $field) {
                unset($before[$party][$field], $after[$party][$field]);
            }
            foreach ($safeContactFields as $field) {
                unset($before[$party][$field], $after[$party][$field]);
            }
        }
        foreach ($before['parcels'] as &$parcel) {
            unset($parcel['content_description']);
        }
        unset($parcel);
        foreach ($after['parcels'] as &$parcel) {
            unset($parcel['content_description']);
        }
        unset($parcel);
        return $before != $after;
    }
}
