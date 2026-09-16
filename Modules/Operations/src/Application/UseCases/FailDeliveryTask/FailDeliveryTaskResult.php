<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\FailDeliveryTask;

final readonly class FailDeliveryTaskResult
{
    public function __construct(public array $data)
    {
    }
}
