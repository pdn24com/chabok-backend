<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\ReplaceRolePermissions;

final readonly class ReplaceRolePermissionsResult
{
    public function __construct(public array $data)
    {
    }
}
