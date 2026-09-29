<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Operations\Application\Contracts\DriverCapabilityWriterInterface;
use Modules\Operations\Application\Repositories\DriverRepositoryInterface;

final readonly class DriverCapabilityWriter implements DriverCapabilityWriterInterface
{
    public function __construct(
        private ClockInterface $clock,
        private DriverRepositoryInterface $driverRepository,
    ) {}

    public function replaceCapabilities(
        ?string $hqId,
        string $driverId,
        array $capabilities,
    ): void {

        $rows = [];
        $now = $this->clock->now();
        foreach ($capabilities as $capability) {
            $rows[] = [

                'hq_id' => $hqId,
                'driver_id' => $driverId,
                'capability' => $capability->value,
                'created_at' => $now,
            ];
        }
        if ($rows !== []) {
            $this->driverRepository->replaceCapabilities($driverId, $rows);
        }
    }
}
