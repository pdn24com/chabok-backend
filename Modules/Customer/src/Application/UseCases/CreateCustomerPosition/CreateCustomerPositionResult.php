<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\CreateCustomerPosition;

use Modules\Customer\Infrastructure\Persistence\Models\CustomerPositionRecord;

/** The stored post. */
final readonly class CreateCustomerPositionResult
{
    public function __construct(
        public CustomerPositionRecord $position,
    ) {}
}
