<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\Ports;

/**
 * Port owned by Pricing, implemented by the Consignment module.
 *
 * @see Modules/Consignment/src/Infrastructure/Adapters/ConsignmentPricingTarget.php (bound in ConsignmentServiceProvider)
 */
interface PricingTargetLookupInterface
{
    public function consignmentExists(string $hqId, string $consignmentId): bool;
}
