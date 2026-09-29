<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Operations\Infrastructure\Persistence\Models\DeliveryTaskRecord;

interface DeliveryTaskReaderInterface
{
    public function locked(AuthenticatedPrincipal $actor, string $nodeId, string $id): DeliveryTaskRecord;
}
