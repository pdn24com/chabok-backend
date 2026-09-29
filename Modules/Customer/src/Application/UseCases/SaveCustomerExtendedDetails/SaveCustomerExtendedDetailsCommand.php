<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\SaveCustomerExtendedDetails;

use Modules\Customer\Application\Dto\CustomerExtendedDetailsDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class SaveCustomerExtendedDetailsCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $customerId,
        public CustomerExtendedDetailsDto $input,
    ) {}
}
