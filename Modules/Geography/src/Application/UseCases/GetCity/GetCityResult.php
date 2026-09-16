<?php

declare(strict_types=1);

namespace Modules\Geography\Application\UseCases\GetCity;

final readonly class GetCityResult
{
    public function __construct(public array $data)
    {
    }
}
