<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\CreateCustomerPosition;

use Modules\Customer\Application\Dto\CustomerPositionDraftDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class CreateCustomerPositionCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $customerId,
        public string $departmentId,
        public CustomerPositionDraftDto $input,
    ) {}
}
