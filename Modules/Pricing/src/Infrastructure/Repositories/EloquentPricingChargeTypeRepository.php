<?php

declare(strict_types=1);

namespace Modules\Pricing\Infrastructure\Repositories;

use Modules\Pricing\Application\Repositories\PricingChargeTypeRepositoryInterface;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingChargeTypeRecord;

final class EloquentPricingChargeTypeRepository implements PricingChargeTypeRepositoryInterface
{
    public function create(array $attributes): string
    {
        return (string) PricingChargeTypeRecord::query()->forceCreate($attributes)->getKey();
    }

    public function sole(string $chargeTypeId): PricingChargeTypeRecord
    {
        return PricingChargeTypeRecord::query()->where('charge_type_id', $chargeTypeId)->sole();
    }

    public function findActive(?string $chargeTypeId): ?PricingChargeTypeRecord
    {
        return PricingChargeTypeRecord::query()->where('active', true)->where('charge_type_id', $chargeTypeId)->first();
    }

    public function idByCode(string $code): ?string
    {
        $id = PricingChargeTypeRecord::query()->where('code', $code)->value('charge_type_id');

        return $id === null ? null : (string) $id;
    }

    public function codesOf(array $chargeTypeIds): array
    {
        return PricingChargeTypeRecord::query()->whereIn('charge_type_id', $chargeTypeIds)->pluck('code')->all();
    }

    public function catalogue(): array
    {
        return PricingChargeTypeRecord::query()->orderBy('code')->get()->all();
    }
}
