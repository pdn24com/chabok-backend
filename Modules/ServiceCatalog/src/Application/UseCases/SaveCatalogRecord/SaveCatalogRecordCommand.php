<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\SaveCatalogRecord;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class SaveCatalogRecordCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $resource,
        public ?string $id,
        public array $input,
        public string $correlation,
    )
    {
    }
}
