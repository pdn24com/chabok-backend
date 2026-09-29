<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Adapters;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Modules\Authorization\Infrastructure\Persistence\Models\AssignmentRecord;
use Modules\Iam\Application\Dto\UserAssignmentDto;
use Modules\Iam\Application\Ports\UserAssignmentReaderInterface;

final class AuthorizationUserAssignmentReader implements UserAssignmentReaderInterface
{
    /** @return list<UserAssignmentDto> */
    public function forUser(string $hqId, string $userId): array
    {
        $assignments = AssignmentRecord::query()
            ->where('hq_id', $hqId)
            ->where('user_id', $userId)
            ->with([
                'scopeArea' => fn (BelongsTo $query) => $query->where('hq_id', $hqId),
                'scopeNode' => fn (BelongsTo $query) => $query->where('hq_id', $hqId),
            ])
            ->orderByDesc('created_at')
            ->get();
        $result = [];
        foreach ($assignments as $assignment) {
            $scopeTitle = match ($assignment->scope_type) {
                'TENANT' => 'کل سازمان',
                'AREA' => $assignment->scopeArea?->area_title ?? 'ناحیه',
                'NODE' => $assignment->scopeNode?->node_title ?? 'گره',
                default => 'حساب شخصی',
            };
            $result[] = new UserAssignmentDto(assignmentId: $assignment->assignment_id, userId: $assignment->user_id, roleId: $assignment->role_id, scopeType: $assignment->scope_type, scopeId: $assignment->scope_id, scopeTitle: $scopeTitle, includesDescendants: (bool) $assignment->includes_descendants, status: $assignment->status);
        }

        return $result;
    }
}
