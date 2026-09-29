<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\SaveCustomerIndustries;

use Modules\Customer\Application\Dto\CustomerIndustryDraftDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class SaveCustomerIndustriesCommand
{
    /**
     * @param  list<CustomerIndustryDraftDto>  $items  the complete set the customer should carry afterwards
     */
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $customerId,
        public array $items,
    ) {}
}
