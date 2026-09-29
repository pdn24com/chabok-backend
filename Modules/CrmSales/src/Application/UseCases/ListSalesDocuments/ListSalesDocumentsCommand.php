<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\UseCases\ListSalesDocuments;

use Modules\CrmSales\Application\Dto\SalesDocumentFiltersDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListSalesDocumentsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public SalesDocumentFiltersDto $filters) {}
}
