<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Adapters;

use Illuminate\Support\Facades\DB;
use Modules\User\Application\Contracts\UserAssignmentReader;

final class AuthorizationUserAssignmentReader implements UserAssignmentReader
{
    public function forUser(string $hqId, string $userId): array
    {
        return DB::table('user_role_assignments')
            ->where('hq_id', $hqId)
            ->where('user_id', $userId)
            ->orderByDesc('created_at')
            ->get([
                'assignment_id',
                'user_id',
                'role_id',
                'scope_type',
                'scope_id',
                'includes_descendants',
                'status',
            ])
            ->map(fn ($row): array => [
                'assignment_id' => (string) $row->assignment_id,
                'user_id' => (string) $row->user_id,
                'role_id' => (string) $row->role_id,
                'scope_type' => (string) $row->scope_type,
                'scope_id' => $row->scope_id === null ? null : (string) $row->scope_id,
                'includes_descendants' => (bool) $row->includes_descendants,
                'status' => (string) $row->status,
            ])
            ->all();
    }
}
