<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\CreateCatalogIdentity;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Application\Dto\CatalogDraftDto;

final readonly class CreateCatalogIdentityCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $resource,
        public CatalogDraftDto $input,
        public string $correlationId,
    ) {}
}
