<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface NumberRangeAccessInterface
{
    public function access(AuthenticatedPrincipal $actor, string $permission): string;
}
