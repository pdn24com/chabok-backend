<?php

declare(strict_types=1);

namespace Modules\CrmOpportunitie\Application\Dto;

/**
 * What the kanban board is drawn for. The funnel decides the columns, so it is always named; the
 * customer and the owner only narrow which cards land in them.
 */
final readonly class OpportunityBoardFiltersDto
{
    public function __construct(
        public string $funnelId,
        public ?string $customerId = null,
        public ?string $assigneeId = null,
    ) {}
}
