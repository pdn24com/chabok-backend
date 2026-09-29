<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

interface DeliveryNodeCapabilitiesInterface
{
    public function nodeHasCapability(string $hqId, string $nodeId, string $capability): bool;
}
