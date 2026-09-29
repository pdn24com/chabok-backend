<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\GetCatalogHistory;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class GetCatalogHistoryCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $resource,
        public string $identityIdValue,
    ) {}
}
