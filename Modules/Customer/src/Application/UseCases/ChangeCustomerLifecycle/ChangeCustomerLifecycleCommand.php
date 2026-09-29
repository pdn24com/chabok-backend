<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\ChangeCustomerLifecycle;

use Modules\Customer\Domain\Enums\CustomerLifecycle;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ChangeCustomerLifecycleCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $customerId,
        public CustomerLifecycle $lifecycle,
        /** Required when the record leaves the active state; optional when it is reopened. */
        public ?string $reason,
    ) {}
}
