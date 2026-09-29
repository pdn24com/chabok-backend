<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ListTariffs;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Pricing\Application\Contracts\PricingAccessGuardInterface;
use Modules\Pricing\Application\Repositories\TariffRepositoryInterface;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffFamilyRecord;

final readonly class ListTariffsHandler
{
    public function __construct(
        private PricingAccessGuardInterface $pricingAccessGuard,
        private TariffRepositoryInterface $tariffRepository,
    ) {}

    public function handle(ListTariffsCommand $command): LengthAwarePaginator
    {
        $actor = $command->actor;
        $filters = $command->filters;
        $this->pricingAccessGuard->assertAccess($actor, 'pricing.tariff.view');

        $page = $this->tariffRepository->paginateFamilies($actor->hqId, max(1, $filters->page), min(100, max(1, $filters->pageSize)), $filters->search);

        return $page->through(static fn (TariffFamilyRecord $row): array => [
            ...$row->attributesToArray(),
            'latest_status' => $row->latestVersion?->status,
            'latest_version_number' => $row->latestVersion?->version_number,
        ]);
    }
}
