<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListDeliveryTasks;

final readonly class ListDeliveryTasksResult
{
    public function __construct(public array $data)
    {
    }
}
