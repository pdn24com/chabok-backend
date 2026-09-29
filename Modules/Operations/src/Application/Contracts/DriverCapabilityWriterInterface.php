<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

interface DriverCapabilityWriterInterface
{
    public function replaceCapabilities(?string $hqId, string $driverId, array $capabilities): void;
}
