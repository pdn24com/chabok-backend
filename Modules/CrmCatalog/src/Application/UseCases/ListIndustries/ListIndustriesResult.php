<?php

declare(strict_types=1);

namespace Modules\CrmCatalog\Application\UseCases\ListIndustries;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\CrmCatalog\Infrastructure\Persistence\Models\IndustryRecord;

/** One page of the industry list. */
final readonly class ListIndustriesResult
{
    /**
     * @param  LengthAwarePaginator<IndustryRecord>  $industries
     */
    public function __construct(
        public LengthAwarePaginator $industries,
    ) {}
}
