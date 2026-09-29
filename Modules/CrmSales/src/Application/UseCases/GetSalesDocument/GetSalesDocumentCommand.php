<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\UseCases\GetSalesDocument;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class GetSalesDocumentCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public string $documentId) {}
}
