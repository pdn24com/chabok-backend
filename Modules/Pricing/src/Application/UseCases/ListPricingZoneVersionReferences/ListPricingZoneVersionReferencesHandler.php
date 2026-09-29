<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ListPricingZoneVersionReferences;

use Illuminate\Pagination\LengthAwarePaginator;
use Modules\Pricing\Application\Contracts\PricingAccessGuardInterface;
use Modules\Pricing\Application\Repositories\PricingZoneRepositoryInterface;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneSetVersionRecord;

final readonly class ListPricingZoneVersionReferencesHandler
{
    public function __construct(
        private PricingAccessGuardInterface $pricingAccessGuard,
        private PricingZoneRepositoryInterface $pricingZoneRepository,
    ) {}

    /** @return LengthAwarePaginator<int, PricingZoneSetVersionRecord> */
    public function handle(ListPricingZoneVersionReferencesCommand $command): LengthAwarePaginator
    {
        $this->pricingAccessGuard->assertAccess($command->actor, 'pricing.tariff.view');
        $filter = $command->search;

        return $this->pricingZoneRepository->paginateVersionReferences($command->actor->hqId, $filter);
    }
}
