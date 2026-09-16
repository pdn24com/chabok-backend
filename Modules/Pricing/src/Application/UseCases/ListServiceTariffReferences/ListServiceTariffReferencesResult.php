<?php

declare(strict_types=1);

namespace Modules\Pricing\Application\UseCases\ListServiceTariffReferences;

final readonly class ListServiceTariffReferencesResult
{
    public function __construct(public array $data)
    {
    }
}
