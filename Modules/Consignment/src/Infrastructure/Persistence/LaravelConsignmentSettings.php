<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Persistence;

use Modules\Consignment\Application\Contracts\ConsignmentSettings;

final class LaravelConsignmentSettings implements ConsignmentSettings
{
    public function editableStatuses(): array
    {
        return (array) config('chabok.consignment.editable_statuses');
    }
}
