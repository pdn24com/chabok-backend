<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetFleetDriver;

final readonly class GetFleetDriverResult
{
    public function __construct(public array $data)
    {
    }
}
