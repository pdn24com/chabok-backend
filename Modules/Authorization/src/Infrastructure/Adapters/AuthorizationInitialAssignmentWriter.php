<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Adapters;

use Modules\Authorization\Application\AuthorizationService;
use Modules\User\Application\Contracts\InitialAssignmentWriter;

final readonly class AuthorizationInitialAssignmentWriter implements InitialAssignmentWriter
{
    public function __construct(private AuthorizationService $authorization) {}

    public function assign(
        string $hqId,
        string $userId,
        string $actorId,
        array $assignments,
        string $correlationId,
    ): void {
        $this->authorization->assignInitial(
            $hqId,
            $userId,
            $actorId,
            $assignments,
            $correlationId,
        );
    }
}
