<?php

declare(strict_types=1);

namespace Modules\Iam\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

/** @mixin UserRecord */
final class UserStatusResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['user_id' => $this->user_id, 'status' => $this->status];
    }
}
