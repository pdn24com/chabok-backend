<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ListPricingAuditEvents;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Modules\Audit\Infrastructure\Persistence\Models\AuditEventRecord;
use Modules\Pricing\Application\Contracts\PricingAccessGuardInterface;
use Modules\Pricing\Application\Repositories\PricingAuditRepositoryInterface;

final readonly class ListPricingAuditEventsHandler
{
    public function __construct(
        private PricingAccessGuardInterface $pricingAccessGuard,
        private PricingAuditRepositoryInterface $pricingAuditRepository,
    ) {}

    public function handle(ListPricingAuditEventsCommand $command): LengthAwarePaginator
    {
        $actor = $command->actor;
        $filters = $command->filters;
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.audit.view');

        $page = $this->pricingAuditRepository->paginatePricingEvents($actor->hqId,
            $filters->targetId === null || $filters->targetId === '' ? null : $filters->targetId,
            max(1, $filters->page), min(100, max(1, $filters->pageSize)));

        // The audit endpoint exposes stored JSON and timestamp strings.
        return $page->through(static fn (AuditEventRecord $row): array => Arr::except($row->getAttributes(), ['id']));
    }
}
