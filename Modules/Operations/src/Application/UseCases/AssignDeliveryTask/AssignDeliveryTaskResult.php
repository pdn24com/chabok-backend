<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\AssignDeliveryTask;

final readonly class AssignDeliveryTaskResult
{
    public function __construct(public array $data)
    {
    }
}
