<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface ConsignmentQuoteAccessInterface
{
    public function assertAccess(
        AuthenticatedPrincipal $actor,
        string $nodeId,
        string $permission,
    ): void;
}
