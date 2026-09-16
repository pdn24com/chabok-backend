<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreateFleetDriver;

final readonly class CreateFleetDriverResult
{
    public function __construct(public array $data)
    {
    }
}
