<?php

declare(strict_types=1);

namespace Modules\User\Infrastructure\Authorization;

use LogicException;
use Modules\User\Application\Contracts\InitialAssignmentWriter;

final class UnavailableInitialAssignmentWriter implements InitialAssignmentWriter
{
    public function assign(
        string $hqId,
        string $userId,
        string $actorId,
        array $assignments,
        string $correlationId,
    ): void {
        throw new LogicException('S0-04 initial assignment writer is not available.');
    }
}
