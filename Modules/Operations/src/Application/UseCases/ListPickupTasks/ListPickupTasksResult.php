<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListPickupTasks;

final readonly class ListPickupTasksResult
{
    public function __construct(public array $data)
    {
    }
}
