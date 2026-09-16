<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\FailPickupTask;

final readonly class FailPickupTaskResult
{
    public function __construct(public array $data)
    {
    }
}
