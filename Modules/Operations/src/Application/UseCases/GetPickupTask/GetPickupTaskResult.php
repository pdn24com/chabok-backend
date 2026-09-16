<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetPickupTask;

final readonly class GetPickupTaskResult
{
    public function __construct(public array $data)
    {
    }
}
