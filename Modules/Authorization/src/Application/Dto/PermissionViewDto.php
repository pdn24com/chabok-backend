<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Dto;

use Modules\Authorization\Infrastructure\Persistence\Models\PermissionRecord;

final readonly class PermissionViewDto
{
    public function __construct(public PermissionRecord $permission, public bool $canGrant) {}
}
