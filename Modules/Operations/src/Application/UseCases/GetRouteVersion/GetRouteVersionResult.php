<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetRouteVersion;

final readonly class GetRouteVersionResult
{
    public function __construct(public array $data)
    {
    }
}
