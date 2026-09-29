<?php

declare(strict_types=1);

namespace Modules\Iam\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Iam\Application\Dto\UserAssignmentDto;

/** @mixin UserAssignmentDto */
final class UserAssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'assignment_id' => $this->assignmentId,
            'user_id' => $this->userId,
            'role_id' => $this->roleId,
            'scope_type' => $this->scopeType,
            'scope_id' => $this->scopeId,
            'scope_title' => $this->scopeTitle,
            'includes_descendants' => $this->includesDescendants,
            'status' => $this->status,
        ];
    }
}
