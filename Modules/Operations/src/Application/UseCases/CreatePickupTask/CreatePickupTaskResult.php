<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CreatePickupTask;

final readonly class CreatePickupTaskResult
{
    public function __construct(public array $data)
    {
    }
}
