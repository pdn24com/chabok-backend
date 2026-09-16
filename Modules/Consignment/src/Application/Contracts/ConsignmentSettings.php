<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Contracts;

interface ConsignmentSettings
{
    /** @return list<string> */

    public function editableStatuses(): array;
}
