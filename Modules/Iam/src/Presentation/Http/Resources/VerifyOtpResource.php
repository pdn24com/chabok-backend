<?php

declare(strict_types=1);

namespace Modules\Iam\Presentation\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Modules\Iam\Application\UseCases\VerifyOtp\VerifyOtpResult;

/** @mixin VerifyOtpResult */
final class VerifyOtpResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return ['verification_token' => $this->verificationToken, 'expires_in' => $this->expiresIn];
    }
}
