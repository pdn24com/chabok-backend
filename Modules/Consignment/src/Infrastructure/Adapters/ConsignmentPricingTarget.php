<?php

declare(strict_types=1);

namespace Modules\Consignment\Infrastructure\Adapters;

use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;
use Modules\Pricing\Application\Ports\PricingTargetLookupInterface;

final class ConsignmentPricingTarget implements PricingTargetLookupInterface
{
    public function consignmentExists(string $hqId, string $consignmentId): bool
    {
        return ConsignmentRecord::query()->where(['hq_id' => $hqId, 'consignment_id' => $consignmentId])->exists();
    }
}
