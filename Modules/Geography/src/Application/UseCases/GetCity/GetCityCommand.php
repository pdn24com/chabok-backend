<?php

declare(strict_types=1);

namespace Modules\Geography\Application\UseCases\GetCity;

final readonly class GetCityCommand
{
    public function __construct(public string $cityId, public bool $activeOnly = true) {}
}
