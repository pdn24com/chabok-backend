<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Contracts;

use Modules\Pricing\Application\Dto\PricingVersionPeriodDto;
use Modules\Pricing\Domain\Enums\PricingResource;
use Modules\Pricing\Infrastructure\Persistence\Models\PricingZoneSetVersionRecord;
use Modules\Pricing\Infrastructure\Persistence\Models\TariffVersionRecord;

interface PricingVersionGuardInterface
{
    public function assertDraft(TariffVersionRecord|PricingZoneSetVersionRecord|null $row, int $expected): void;

    public function hasVersionOverlap(PricingResource $kind, PricingVersionPeriodDto $period): bool;

    public function defaultConflict(TariffVersionRecord $version, string $hqId): bool;
}
