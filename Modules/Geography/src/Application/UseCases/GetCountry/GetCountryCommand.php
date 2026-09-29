<?php

declare(strict_types=1);

namespace Modules\Geography\Application\UseCases\GetCountry;

final readonly class GetCountryCommand
{
    public function __construct(public string $countryId) {}
}
