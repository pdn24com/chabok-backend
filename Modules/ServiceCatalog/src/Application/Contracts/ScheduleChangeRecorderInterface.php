<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface ScheduleChangeRecorderInterface
{
    public function record(AuthenticatedPrincipal $actor, string $action, string $id, string $correlationId, array $after): void;
}
