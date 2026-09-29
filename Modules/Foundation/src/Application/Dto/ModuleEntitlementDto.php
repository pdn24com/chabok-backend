<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Dto;

use Modules\Foundation\Domain\Enums\EntitlementStatus;

final readonly class ModuleEntitlementDto
{
    public function __construct(public string $moduleCode, public EntitlementStatus $status) {}
}
