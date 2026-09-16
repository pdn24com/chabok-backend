<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateOperationalRoute;

final readonly class CreateOperationalRouteResult
{
    public function __construct(public string $data)
    {
    }
}
