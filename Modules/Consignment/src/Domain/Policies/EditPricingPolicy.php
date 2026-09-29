<?php

declare(strict_types=1);

namespace Modules\Consignment\Domain\Policies;

use Modules\Consignment\Application\Dto\ConsignmentDraftDto;

final class EditPricingPolicy
{
    public function changed(
        ConsignmentDraftDto $before,
        ConsignmentDraftDto $after,
        array $safeContactFields,
    ): bool {
        $before = clone $before;
        $after = clone $after;
        $before->sender = clone $before->sender;
        $before->receiver = clone $before->receiver;
        $after->sender = clone $after->sender;
        $after->receiver = clone $after->receiver;
        $before->presentFields = $after->presentFields = [];
        foreach ([$before->sender, $before->receiver, $after->sender, $after->receiver] as $contact) {
            $contact->cityReference = null;
            $contact->provinceId = null;
            $contact->legacyCityCode = null;
            $contact->presentFields = [];
            if (in_array('contact_name', $safeContactFields, true)) {
                $contact->contactName = null;
            }
            if (in_array('mobile', $safeContactFields, true)) {
                $contact->mobile = null;
            }
            if (in_array('phone', $safeContactFields, true)) {
                $contact->phone = null;
            }
            if (in_array('address_text', $safeContactFields, true)) {
                $contact->addressText = null;
            }
            if (in_array('latitude', $safeContactFields, true)) {
                $contact->latitude = null;
            }
            if (in_array('longitude', $safeContactFields, true)) {
                $contact->longitude = null;
            }
            if (in_array('postal_code', $safeContactFields, true)) {
                $contact->postalCode = null;
            }
        }
        foreach ([$before, $after] as $draft) {
            $parcels = [];
            foreach ($draft->parcels as $original) {
                $parcel = clone $original;
                $parcel->contentDescription = null;
                $parcel->presentFields = [];
                $parcels[] = $parcel;
            }
            $draft->parcels = $parcels;
        }

        return $before != $after;
    }
}
