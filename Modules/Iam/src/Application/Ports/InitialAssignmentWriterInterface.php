<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Ports;

use Modules\Foundation\Domain\ValueObjects\RoleAssignment;

/**
 * Port owned by Iam, implemented by the Authorization module.
 *
 * @see Modules/Authorization/src/Infrastructure/Adapters/AuthorizationInitialAssignmentWriter.php (bound in AuthorizationServiceProvider)
 */
interface InitialAssignmentWriterInterface
{
    /**
     * @param  list<RoleAssignment>  $assignments
     */
    public function assign(
        string $hqId,
        string $userId,
        string $actorId,
        array $assignments,
        string $correlationId,
    ): void;
}
