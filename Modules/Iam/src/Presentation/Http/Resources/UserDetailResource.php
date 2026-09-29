<?php

declare(strict_types=1);

namespace Modules\Iam\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Foundation\Presentation\Http\Resources\SessionSummaryResource;
use Modules\Iam\Application\Dto\UserDetailDto;

/** @mixin UserDetailDto */
final class UserDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'user' => (new UserResource($this->user))->resolve($request),
            'assignments' => UserAssignmentResource::collection($this->assignments)->resolve($request),
            'invitation_status' => $this->invitationStatus,
            'driver_profile' => $this->driverProfile === null ? null : (new DriverProfileResource($this->driverProfile))->resolve($request),
            'sessions' => SessionSummaryResource::collection($this->sessions)->resolve($request),
        ];
    }
}
