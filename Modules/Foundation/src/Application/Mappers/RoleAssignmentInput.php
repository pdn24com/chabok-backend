<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Mappers;

use Modules\Foundation\Domain\Enums\ScopeType;
use Modules\Foundation\Domain\ValueObjects\PermissionScope;
use Modules\Foundation\Domain\ValueObjects\RoleAssignment;

final class RoleAssignmentInput
{
    public static function draft(array $input): RoleAssignment
    {
        return new RoleAssignment($input['role_id'], new PermissionScope(ScopeType::from($input['scope_type']),
            $input['scope_id'] ?? null, (bool) $input['includes_descendants']));
    }

    /** @return list<RoleAssignment> */
    public static function drafts(array $input): array
    {
        return array_map(self::draft(...), $input);
    }
}
