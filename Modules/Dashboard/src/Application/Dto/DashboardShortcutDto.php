<?php

declare(strict_types=1);

namespace Modules\Dashboard\Application\Dto;

final readonly class DashboardShortcutDto
{
    public function __construct(public string $key, public DashboardCapabilityDto $capability, public ?string $target, public bool $implemented) {}
}
