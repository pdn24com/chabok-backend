<?php

declare(strict_types=1);

namespace Modules\Authorization\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Authorization\Application\Dto\PermissionViewDto;

/** @mixin PermissionViewDto */
final class PermissionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'permission_id' => $this->permission->permission_id,
            'permission_code' => $this->permission->permission_code,
            'module_code' => $this->permission->module_code,
            'description' => $this->permission->description,
            'status' => $this->permission->status,
            'can_grant' => $this->canGrant,
        ];
    }
}
