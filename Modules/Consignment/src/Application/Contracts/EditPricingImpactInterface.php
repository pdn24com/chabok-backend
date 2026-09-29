<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Contracts;

use Modules\Consignment\Application\Dto\ConsignmentDraftDto;
use Modules\Consignment\Infrastructure\Persistence\Models\ConsignmentRecord;

interface EditPricingImpactInterface
{
    /** Fields which do not affect the accepted tariff or its geographic dependencies. */
    public function contactFields(ConsignmentRecord $row): array;

    public function changed(
        ConsignmentDraftDto $before,
        ConsignmentDraftDto $after,
        array $safeContactFields,
    ): bool;
}
