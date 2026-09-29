<?php

declare(strict_types=1);

namespace Modules\Iam\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

/** @mixin UserRecord */
final class UserResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user_id' => $this->user_id,
            'hq_id' => $this->hq_id,
            'username' => $this->username,
            'mobile' => $this->mobile,
            'email' => $this->email,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'display_name' => $this->display_name,
            'status' => $this->status,
            'must_change_password' => $this->must_change_password,
        ];
    }
}
