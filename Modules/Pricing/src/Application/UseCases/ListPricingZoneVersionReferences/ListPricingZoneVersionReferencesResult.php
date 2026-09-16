<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ListPricingZoneVersionReferences;

use Modules\Foundation\Application\Data\Page;

final readonly class ListPricingZoneVersionReferencesResult
{
    public function __construct(public Page $data)
    {
    }
}
