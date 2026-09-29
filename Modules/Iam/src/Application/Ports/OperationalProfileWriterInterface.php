<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Ports;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Foundation\Domain\ValueObjects\RoleAssignment;
use Modules\Iam\Application\Dto\DriverProfileSummaryDto;
use Modules\Iam\Application\Dto\OperationalProfileInputDto;

/**
 * Port owned by Iam, implemented by the application layer.
 *
 * @see app/Infrastructure/Adapters/OperationalProfileAdapter.php (bound in AppServiceProvider)
 */
interface OperationalProfileWriterInterface
{
    /** Returns an optional Node assignment to append inside the caller's transaction. */
    public function attach(
        AuthenticatedPrincipal $actor,
        string $userId,
        OperationalProfileInputDto $input,
        string $correlationId,
    ): ?RoleAssignment;

    public function forUser(AuthenticatedPrincipal $actor, string $userId): ?DriverProfileSummaryDto;
}
