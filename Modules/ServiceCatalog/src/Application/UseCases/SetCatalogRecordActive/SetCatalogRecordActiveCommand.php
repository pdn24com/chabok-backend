<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\SetCatalogRecordActive;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class SetCatalogRecordActiveCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $resource,
        public string $id,
        public bool $active,
        public int $expected,
        public string $correlation,
    ) {}
}
