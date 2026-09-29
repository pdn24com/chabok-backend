<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Authorization\Application\Repositories\AssignmentRepositoryInterface;
use Modules\Authorization\Infrastructure\Persistence\Models\AssignmentRecord;

final class EloquentAssignmentRepository implements AssignmentRepositoryInterface
{
    public function lockForUser(string $assignmentId, string $userId): ?AssignmentRecord
    {
        return AssignmentRecord::query()
            ->where(['assignment_id' => $assignmentId, 'user_id' => $userId])
            ->lockForUpdate()
            ->first();
    }

    public function activeForUserInTenant(string $userId, ?string $hqId): Collection
    {
        return AssignmentRecord::query()->with('role')->where('user_id', $userId)->where('hq_id', $hqId)
            ->where('status', 'ACTIVE')->whereHas('role', fn ($role) => $role->where('status', 'ACTIVE'))->get();
    }

    public function activeUserIdsForRole(string $roleId): array
    {
        return AssignmentRecord::query()
            ->where('role_id', $roleId)
            ->where('status', 'ACTIVE')
            ->pluck('user_id')
            ->all();
    }

    public function hasPlatformSuperAdmin(string $userId): bool
    {
        return AssignmentRecord::query()->where('user_id', $userId)->whereNull('hq_id')->where('status', 'ACTIVE')
            ->where('scope_type', 'PLATFORM')->whereNull('scope_id')
            ->whereHas('user', fn ($user) => $user->whereNull('hq_id')->where('status', 'ACTIVE'))
            ->whereHas('role', fn ($role) => $role->where('role_code', 'platform_super_admin')->where('status', 'ACTIVE'))
            ->exists();
    }

    public function occupiedSlots(array $activeSlots): array
    {
        return AssignmentRecord::query()->whereIn('active_slot', $activeSlots)->pluck('active_slot')->all();
    }

    public function insert(array $rows): void
    {
        AssignmentRecord::query()->insert($rows);
    }

    public function byActiveSlots(string $hqId, array $activeSlots): Collection
    {
        return AssignmentRecord::query()->where('hq_id', $hqId)->whereIn('active_slot', $activeSlots)->get()->keyBy('active_slot');
    }

    public function update(string $assignmentId, array $changes): void
    {
        AssignmentRecord::query()->where('assignment_id', $assignmentId)->update($changes);
    }
}
