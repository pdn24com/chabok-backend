<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Application\Dto;

/** What the funnel list asks for. Null means both the active and the retired funnels. */
final readonly class SalesFunnelFiltersDto
{
    public function __construct(public ?bool $active = null) {}
}
