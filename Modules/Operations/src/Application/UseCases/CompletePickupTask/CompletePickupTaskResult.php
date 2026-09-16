<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CompletePickupTask;

final readonly class CompletePickupTaskResult
{
    public function __construct(public array $data)
    {
    }
}
