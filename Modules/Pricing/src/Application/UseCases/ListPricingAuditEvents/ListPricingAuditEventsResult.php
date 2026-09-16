<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ListPricingAuditEvents;

use Modules\Foundation\Application\Data\Page;

final readonly class ListPricingAuditEventsResult
{
    public function __construct(public Page $data)
    {
    }
}
