<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Persistence;

use Modules\Consignment\Application\Contracts\ConsignmentSettingsInterface;

final class LaravelConsignmentSettings implements ConsignmentSettingsInterface
{
    public function editableStatuses(): array
    {
        return (array) config('chabok.consignment.editable_statuses');
    }
}
