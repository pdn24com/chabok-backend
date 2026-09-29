<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Services;

use Modules\Customer\Infrastructure\Persistence\Models\CustomerRecord;

/**
 * The core profile data a customer record is still missing, always in the same order so the panel that
 * renders it never reshuffles. A record with no address at all reports the address itself and not its
 * fields: the operator adds an address once, rather than a postal code that belongs to nothing.
 */
final class CustomerProfileGaps
{
    /** @return list<string> */
    public static function of(CustomerRecord $customer): array
    {
        $address = $customer->defaultAddress;

        return array_keys(array_filter([
            'customer_code' => blank($customer->customer_code),
            'display_name' => blank($customer->display_name),
            'first_name' => blank($customer->first_name),
            'family_name' => blank($customer->family_name),
            'mobile' => blank($customer->defaultMobile?->value),
            'assignee' => $customer->assignee_id === null,
            'primary_industry' => $customer->primaryIndustry === null,
            'default_address' => $address === null,
            'postal_code' => $address !== null && blank($address->postal_code),
            'address_text' => $address !== null && blank($address->address_text),
        ]));
    }
}
