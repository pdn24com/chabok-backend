<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\UseCases\CreateSalesDocumentVersion;

use Modules\CrmSales\Application\Dto\SalesDocumentRevisionDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class CreateSalesDocumentVersionCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $documentId,
        public SalesDocumentRevisionDto $input,
    ) {}
}
