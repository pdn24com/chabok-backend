<?php

declare(strict_types=1);

namespace Modules\Iam\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Foundation\Presentation\Http\Resources\AccessContextResource;
use Modules\Iam\Application\Dto\IssuedSessionDto;

/** @mixin IssuedSessionDto */
final class IssuedSessionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            ...(new SessionCredentialsResource($this->credentials))->resolve($request),
            'session_id' => $this->sessionId,
            'must_change_password' => $this->user->must_change_password,
            'user' => (new UserResource($this->user))->resolve($request),
            'context' => (new AccessContextResource($this->context))->resolve($request),
        ];
    }
}
