<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Repositories;

use Modules\Pricing\Infrastructure\Persistence\Models\PricingChargeTypeRecord;

interface PricingChargeTypeRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): string;

    public function sole(string $chargeTypeId): PricingChargeTypeRecord;

    public function findActive(?string $chargeTypeId): ?PricingChargeTypeRecord;

    public function idByCode(string $code): ?string;

    /** @param list<string> $chargeTypeIds @return list<string> */
    public function codesOf(array $chargeTypeIds): array;

    /** @return list<PricingChargeTypeRecord> */
    public function catalogue(): array;
}
