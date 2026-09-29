<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ResolveServiceOfferings;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\ServiceCatalog\Domain\ValueObjects\OfferingSelectionContext;

final readonly class ResolveServiceOfferingsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public OfferingSelectionContext $context) {}
}
