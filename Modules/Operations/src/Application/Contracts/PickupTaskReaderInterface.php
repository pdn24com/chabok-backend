<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Infrastructure\Persistence\Models\PickupTaskRecord;

interface PickupTaskReaderInterface
{
    public function locked(AuthenticatedPrincipal $actor, string $nodeId, string $id): PickupTaskRecord;
}
