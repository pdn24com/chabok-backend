<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\UpdateCustomerPosition;

use Modules\Customer\Infrastructure\Persistence\Models\CustomerPositionRecord;

/** The post as the change left it. */
final readonly class UpdateCustomerPositionResult
{
    public function __construct(
        public CustomerPositionRecord $position,
    ) {}
}
