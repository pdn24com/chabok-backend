<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\RetryDeliveryTask;

final readonly class RetryDeliveryTaskResult
{
    public function __construct(public array $data)
    {
    }
}
