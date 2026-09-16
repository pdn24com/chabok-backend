<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\UpdateFleetDriver;

final readonly class UpdateFleetDriverResult
{
    public function __construct(public array $data)
    {
    }
}
