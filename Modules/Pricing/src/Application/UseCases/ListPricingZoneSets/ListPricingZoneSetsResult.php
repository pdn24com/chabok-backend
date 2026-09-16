<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ListPricingZoneSets;

use Modules\Foundation\Application\Data\Page;

final readonly class ListPricingZoneSetsResult
{
    public function __construct(public Page $data)
    {
    }
}
