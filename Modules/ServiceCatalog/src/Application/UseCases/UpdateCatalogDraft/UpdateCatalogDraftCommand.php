<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\UpdateCatalogDraft;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Application\Dto\CatalogDraftDto;

final readonly class UpdateCatalogDraftCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $resource,
        public string $versionIdValue,
        public int $expectedVersion,
        public CatalogDraftDto $input,
        public string $correlationId,
    ) {}
}
