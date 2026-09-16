<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ValidateCatalogDraft;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ValidateCatalogDraftCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $resource,
        public string $versionIdValue,
        public bool $automatic = false,
    )
    {
    }
}
