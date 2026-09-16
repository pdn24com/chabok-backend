<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\CloneCatalogDraft;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CloneCatalogDraftCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $resource,
        public string $identityIdValue,
        public string $correlationId,
    )
    {
    }
}
