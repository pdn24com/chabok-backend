<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\ChangeCustomerLifecycle;

use Modules\Customer\Domain\Enums\CustomerLifecycle;

/** The new state, and the note that recorded why, when there is one. */
final readonly class ChangeCustomerLifecycleResult
{
    public function __construct(
        public string $customerId,
        public CustomerLifecycle $lifecycle,
        public ?string $activityId,
    ) {}
}
