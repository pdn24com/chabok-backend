<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\SaveCatalogRecord;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Application\Dto\CatalogDraftDto;
use Modules\ServiceCatalog\Application\Dto\CommitmentScheduleDto;

final readonly class SaveCatalogRecordCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $resource,
        public ?string $id,
        public CatalogDraftDto|CommitmentScheduleDto $input,
        public string $correlation,
    ) {}
}
