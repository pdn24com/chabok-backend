<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\TransitionCatalogVersion;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class TransitionCatalogVersionCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $resource,
        public string $versionIdValue,
        public string $action,
        public string $correlationId,
    ) {}
}
