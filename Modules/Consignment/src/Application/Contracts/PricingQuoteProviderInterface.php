<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Contracts;

use Modules\Consignment\Application\Dto\ConsignmentPricingOptionDto;
use Modules\Consignment\Application\Dto\ConsignmentPricingRequestDto;

interface PricingQuoteProviderInterface
{
    /**
     * @return list<ConsignmentPricingOptionDto>
     */
    public function calculate(ConsignmentPricingRequestDto $request): array;
}
