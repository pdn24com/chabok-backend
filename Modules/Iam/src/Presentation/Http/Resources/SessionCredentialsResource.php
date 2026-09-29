<?php

declare(strict_types=1);

namespace Modules\Iam\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Iam\Application\Dto\SessionCredentialsDto;

/** @mixin SessionCredentialsDto */
final class SessionCredentialsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // Refresh credentials belong only in the HttpOnly cookie.
        return ['access_token' => $this->accessToken->token, 'expires_in' => $this->accessToken->expiresIn];
    }
}
