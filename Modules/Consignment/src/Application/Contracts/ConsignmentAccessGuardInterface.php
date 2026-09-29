<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Contracts;

use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface ConsignmentAccessGuardInterface
{
    public function assertAccess(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $permission,
    ): AccessContextDto;
}
