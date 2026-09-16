<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\GetDeliveryTask;

final readonly class GetDeliveryTaskResult
{
    public function __construct(public array $data)
    {
    }
}
