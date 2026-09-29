<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\UpdateCustomerProfile;

use Modules\Customer\Application\Dto\CustomerProfileChangesDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class UpdateCustomerProfileCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $customerId,
        public CustomerProfileChangesDto $changes,
    ) {}
}
