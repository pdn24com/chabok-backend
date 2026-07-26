<?php

declare(strict_types=1);

namespace Modules\User\Application\Contracts;

interface InitialAssignmentWriter
{
    /**
     * @param list<array<string, mixed>> $assignments
     */
    public function assign(
        string $hqId,
        string $userId,
        string $actorId,
        array $assignments,
        string $correlationId,
    ): void;
}
