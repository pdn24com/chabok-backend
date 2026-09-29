<?php

declare(strict_types=1);

namespace Modules\Authorization\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class AssignmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'assignment_id' => $this->assignment_id,
            'user_id' => $this->user_id,
            'role_id' => $this->role_id,
            'scope_type' => $this->scope_type,
            'scope_id' => $this->scope_id,
            'includes_descendants' => (bool) $this->includes_descendants,
            'status' => $this->status,
        ];
    }
}
