<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ListCatalogAuditEvents;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListCatalogAuditEventsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public array $filters) {}
}
