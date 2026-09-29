<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\CommitmentScheduleVersionRecord;

interface ScheduleReaderInterface
{
    public function versionDetail(AuthenticatedPrincipal $actor, string $versionId): CommitmentScheduleVersionRecord;
}
