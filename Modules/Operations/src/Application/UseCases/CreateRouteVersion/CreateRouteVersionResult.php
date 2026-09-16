<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateRouteVersion;

final readonly class CreateRouteVersionResult
{
    public function __construct(public array $data)
    {
    }
}
