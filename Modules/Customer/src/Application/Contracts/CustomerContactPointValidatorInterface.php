<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Contracts;

use Modules\Customer\Application\Dto\CustomerContactPointDraftDto;

interface CustomerContactPointValidatorInterface
{
    /**
     * Judges the whole contact-point set of one person and returns the normalised value of every item, in
     * the order the items were given. The rules cover the identifier kind against the channel type, the
     * per-channel normalisation, the person's own relationships and addresses, duplicates and defaults
     * inside the set, and the one-person-per-mobile rule against the rest of the tenant. It must run
     * inside the transaction that writes the set and that has already locked the person's row.
     *
     * @param  list<CustomerContactPointDraftDto>  $items
     * @param  list<string>  $existingIds  the IDs of the contact points the person holds now
     * @return list<string>
     */
    public function validate(string $hqId, string $customerId, array $items, array $existingIds): array;
}
