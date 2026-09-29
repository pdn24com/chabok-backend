<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Mappers;

use Modules\Authorization\Application\Dto\RoleChangesDto;
use Modules\Authorization\Application\Dto\RoleDraftDto;
use Modules\Authorization\Domain\Enums\RoleStatus;

final class RoleInput
{
    public static function draft(array $input): RoleDraftDto
    {
        return new RoleDraftDto($input['role_code'], $input['role_title'], $input['description'] ?? null,
            $input['permission_codes'] ?? null, $input['menu_keys'] ?? null, array_key_exists('menu_keys', $input));
    }

    public static function changes(array $input): RoleChangesDto
    {
        return new RoleChangesDto($input['role_title'] ?? null, $input['description'] ?? null,
            array_key_exists('description', $input), isset($input['status']) ? RoleStatus::from($input['status']) : null,
            $input['permission_codes'] ?? null, $input['menu_keys'] ?? null, array_key_exists('menu_keys', $input));
    }
}
