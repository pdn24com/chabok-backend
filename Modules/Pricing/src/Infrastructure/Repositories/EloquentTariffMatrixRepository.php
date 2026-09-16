<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Repositories;

use Modules\Pricing\Application\Repositories\TariffMatrixRepository;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingChargeTypeRecord;

final class EloquentTariffMatrixRepository implements TariffMatrixRepository
{
    public function baseFreightChargeId(): ?string
    {
        return PricingChargeTypeRecord::query()->toBase()->where('code', 'BASE_FREIGHT')->value('charge_type_id');
    }

    public function activeCharge(?string $id): ?object
    {
        return PricingChargeTypeRecord::query()->toBase()->where('charge_type_id', $id)->where('active', true)->first();
    }
}
