<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ListPricingZoneSets;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Pricing\Application\Contracts\PricingAccessGuardInterface;
use Modules\Pricing\Application\Repositories\PricingZoneRepositoryInterface;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneSetRecord;

final readonly class ListPricingZoneSetsHandler
{
    public function __construct(
        private PricingAccessGuardInterface $pricingAccessGuard,
        private PricingZoneRepositoryInterface $pricingZoneRepository,
    ) {}

    public function handle(ListPricingZoneSetsCommand $command): LengthAwarePaginator
    {
        $actor = $command->actor;
        $filters = $command->filters;
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.view');

        $page = $this->pricingZoneRepository->paginateZoneSets($actor->hqId, max(1, $filters->page), min(100, max(1, $filters->pageSize)), $filters->search);

        return $page->through(static fn (PricingZoneSetRecord $row): array => [
            ...$row->attributesToArray(),
            'latest_status' => $row->latestVersion?->status,
            'latest_version_number' => $row->latestVersion?->version_number,
        ]);
    }
}
