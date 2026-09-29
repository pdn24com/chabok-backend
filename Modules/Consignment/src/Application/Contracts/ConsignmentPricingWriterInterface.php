<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Contracts;

use Modules\Consignment\Application\Dto\AcceptedConsignmentQuoteDto;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;

interface ConsignmentPricingWriterInterface
{
    public function persistPricing(
        ConsignmentRecord $consignment,
        int $version,
        string $actorId,
        AcceptedConsignmentQuoteDto $accepted,
    ): string;
}
