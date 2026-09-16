<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateOperationalRoute;

final readonly class CreateOperationalRouteCommand
{
    public function __construct(public string $hqId, public string $code, public string $title, public array $legs)
    {
    }
}
