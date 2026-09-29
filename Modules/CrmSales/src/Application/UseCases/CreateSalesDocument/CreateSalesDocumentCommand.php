<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\UseCases\CreateSalesDocument;

use Modules\CrmSales\Application\Dto\SalesDocumentDraftDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class CreateSalesDocumentCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public SalesDocumentDraftDto $input) {}
}
