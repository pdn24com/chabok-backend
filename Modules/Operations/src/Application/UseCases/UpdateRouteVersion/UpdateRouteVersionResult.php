<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\UpdateRouteVersion;

final readonly class UpdateRouteVersionResult
{
    public function __construct(public array $data)
    {
    }
}
