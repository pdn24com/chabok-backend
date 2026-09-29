<?php

declare(strict_types=1);

namespace Modules\Iam\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Iam\Application\UseCases\SendOtp\SendOtpResult;

/** @mixin SendOtpResult */
final class SendOtpResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['challenge_id' => $this->challengeId, 'expires_in' => $this->expiresIn];
    }
}
