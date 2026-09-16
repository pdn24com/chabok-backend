<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\EnsurePendingDelivery;

final readonly class EnsurePendingDeliveryResult
{
    public function __construct(public string $data)
    {
    }
}
