<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Modules\Operations\Infrastructure\Persistence\Models\RoutePlanRecord;

interface RoutePlanGuardInterface
{
    public function version(RoutePlanRecord $row, int $expected, string $label): void;

    public function assertPlanConfigurationAvailable(RoutePlanRecord $plan): void;
}
