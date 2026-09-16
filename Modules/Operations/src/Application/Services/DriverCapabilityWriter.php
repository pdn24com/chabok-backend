<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

final readonly class DriverCapabilityWriter
{
    public function __construct(
        private \Modules\Operations\Application\Repositories\FleetRepository $fleet,
        private \Modules\Foundation\Application\Contracts\IdentifierGenerator $identifiers,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
    )
    {
    }

    public function replaceCapabilities(?string $hqId, string $driverId, array $capabilities): void
    {
        $this->fleet->removeCapabilities($driverId);
        foreach ($capabilities as $capability) {
            $this->fleet->insertCapability([
                'driver_capability_id' => $this->identifiers->uuid(),
                'hq_id' => $hqId,
                'driver_id' => $driverId,
                'capability' => $capability,
                'created_at' => $this->clock->now(),
            ]);
        }
    }
}
