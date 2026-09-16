<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Repositories;

interface TariffMatrixRepository
{
    public function baseFreightChargeId(): ?string;

    public function activeCharge(?string $id): ?object;
}
