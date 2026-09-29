<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\SaveCustomerExtendedDetails;

use Modules\Customer\Infrastructure\Persistence\Models\CustomerExtendedDetailRecord;

/** The extended-details row as the save left it. */
final readonly class SaveCustomerExtendedDetailsResult
{
    public function __construct(
        public CustomerExtendedDetailRecord $details,
    ) {}
}
