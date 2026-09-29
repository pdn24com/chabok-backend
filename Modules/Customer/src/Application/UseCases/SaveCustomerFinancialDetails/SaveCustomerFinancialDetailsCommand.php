<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\SaveCustomerFinancialDetails;

use Modules\Customer\Application\Dto\CustomerFinancialDetailsDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class SaveCustomerFinancialDetailsCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $customerId,
        public CustomerFinancialDetailsDto $input,
    ) {}
}
