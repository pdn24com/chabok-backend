<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\TransitionRouteVersion;

final readonly class TransitionRouteVersionResult
{
    public function __construct(public array $data)
    {
    }
}
