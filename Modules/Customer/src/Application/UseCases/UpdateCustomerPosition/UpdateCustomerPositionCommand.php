<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\UpdateCustomerPosition;

use Modules\Customer\Application\Dto\CustomerPositionChangesDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class UpdateCustomerPositionCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $customerId,
        public string $positionId,
        public CustomerPositionChangesDto $changes,
    ) {}
}
