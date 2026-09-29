<?php

declare(strict_types=1);

namespace Modules\Dashboard\Application\Dto;

final readonly class DashboardCapabilitiesDto
{
    public function __construct(public DashboardCapabilityDto $consignment, public DashboardCapabilityDto $manifest, public DashboardCapabilityDto $pickup, public DashboardCapabilityDto $driver, public DashboardCapabilityDto $nok, public DashboardCapabilityDto $npu, public DashboardCapabilityDto $audit) {}
}
