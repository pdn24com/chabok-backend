<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\ServiceCatalog\Application\Dto\CommitmentScheduleDto;

interface ScheduleInputInterface
{
    public function versionColumns(CommitmentScheduleDto $input): array;

    public function databaseTimestamp(?string $value): ?string;
}
