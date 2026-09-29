<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Contracts;

use Modules\Consignment\Application\Dto\ConsignmentDraftDto;

interface ConsignmentQuoteInputInterface
{
    public function normalized(ConsignmentDraftDto $source): ConsignmentDraftDto;
}
