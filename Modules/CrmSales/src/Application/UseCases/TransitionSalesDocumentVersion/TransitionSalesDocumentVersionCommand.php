<?php

declare(strict_types=1);

namespace Modules\CrmSales\Application\UseCases\TransitionSalesDocumentVersion;

use Modules\CrmSales\Domain\Enums\SalesDocumentTransition;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class TransitionSalesDocumentVersionCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $versionId,
        public SalesDocumentTransition $transition,
    ) {}
}
