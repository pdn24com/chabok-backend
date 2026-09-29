<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\FindCustomerDuplicates;

use Modules\Customer\Application\Dto\CustomerDuplicateDto;

/** The records already holding the looked-up mobile number or email address, newest first. */
final readonly class FindCustomerDuplicatesResult
{
    /**
     * @param  list<CustomerDuplicateDto>  $duplicates
     */
    public function __construct(
        public array $duplicates,
    ) {}
}
