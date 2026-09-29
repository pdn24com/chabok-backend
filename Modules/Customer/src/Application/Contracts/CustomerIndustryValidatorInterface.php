<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Contracts;

use Modules\Customer\Application\Dto\CustomerIndustryDraftDto;

interface CustomerIndustryValidatorInterface
{
    /**
     * Judges the industry set of one customer: no industry twice, at most one primary, and every industry
     * an active one of the tenant. An empty set is valid and clears the customer's industries.
     *
     * @param  list<CustomerIndustryDraftDto>  $items
     */
    public function validate(string $hqId, array $items): void;
}
