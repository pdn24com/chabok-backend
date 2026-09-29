<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Application\UseCases\ListSalesFunnels;

use Illuminate\Database\Eloquent\Collection;
use Modules\CrmOpportunitie\Infrastructure\Persistence\Models\SalesFunnelRecord;

/** The funnels of the tenant, each carrying the active steps the board draws its columns from. */
final readonly class ListSalesFunnelsResult
{
    /**
     * @param  Collection<int, SalesFunnelRecord>  $funnels
     */
    public function __construct(
        public Collection $funnels,
    ) {}
}
