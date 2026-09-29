<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\ServiceCatalog\Application\Dto\CommitmentScheduleDto;

interface ScheduleChildrenWriterInterface
{
    public function replaceChildren(string $versionId, string $hqId, CommitmentScheduleDto $input): void;
}
