<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\UseCases\ResolveServiceOfferings;

final readonly class ResolveServiceOfferingsResult
{
    public function __construct(public array $data)
    {
    }
}
