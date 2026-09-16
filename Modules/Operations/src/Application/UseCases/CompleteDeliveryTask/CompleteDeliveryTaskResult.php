<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\CompleteDeliveryTask;

final readonly class CompleteDeliveryTaskResult
{
    public function __construct(public array $data)
    {
    }
}
