<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ServiceTypeVersionRecord;
use Modules\ServiceCatalog\Infrastructure\Persistence\Models\ShippingMethodVersionRecord;

final readonly class OfferingDependencyVersionsDto
{
    public function __construct(public ?ServiceTypeVersionRecord $type, public ?ShippingMethodVersionRecord $method) {}

    public function available(): bool
    {
        return $this->type !== null && $this->method !== null;
    }
}
