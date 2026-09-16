<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ActivateManifestDelivery;

final readonly class ActivateManifestDeliveryResult
{
    public function __construct(public string $data)
    {
    }
}
