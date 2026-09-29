<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface CatalogAccessGuardInterface
{
    public function assertAccess(AuthenticatedPrincipal $actor, string $permission, bool $runtime = false): void;

    public function authorizeRecord(AuthenticatedPrincipal $actor, bool $write = false): void;
}
