<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Contracts;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

interface CatalogChangeRecorderInterface
{
    public function record(AuthenticatedPrincipal $actor, string $action, string $type, string $id, string $correlationId, array $after): void;
}
