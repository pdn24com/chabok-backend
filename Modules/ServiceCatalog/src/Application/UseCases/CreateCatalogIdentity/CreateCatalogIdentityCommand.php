<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\CreateCatalogIdentity;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CreateCatalogIdentityCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $resource,
        public array $input,
        public string $correlationId,
    )
    {
    }
}
