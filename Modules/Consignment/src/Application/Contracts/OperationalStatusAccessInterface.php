<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Contracts;

use Modules\Foundation\Application\Dto\AccessContextDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface OperationalStatusAccessInterface
{
    public function access(AuthenticatedPrincipal $actor, bool $write): AccessContextDto;

    public function tenantManager(AccessContextDto $context): bool;
}
