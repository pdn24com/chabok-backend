<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Application\UseCases\ListSalesFunnels;

use Modules\CrmOpportunitie\Application\Dto\SalesFunnelFiltersDto;
use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;

final readonly class ListSalesFunnelsCommand
{
    public function __construct(public AuthenticatedPrincipal $actor, public SalesFunnelFiltersDto $filters) {}
}
