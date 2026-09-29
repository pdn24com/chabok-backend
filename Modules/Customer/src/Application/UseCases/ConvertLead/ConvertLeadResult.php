<?php

declare(strict_types=1);

namespace Modules\Customer\Application\UseCases\ConvertLead;

use Modules\Customer\Infrastructure\Persistence\Models\CustomerRecord;

/** The record after it left the lead phase; its identity did not change. */
final readonly class ConvertLeadResult
{
    public function __construct(
        public CustomerRecord $customer,
    ) {}
}
