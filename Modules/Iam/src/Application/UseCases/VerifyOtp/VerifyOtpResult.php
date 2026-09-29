<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\VerifyOtp;

final readonly class VerifyOtpResult
{
    public function __construct(public string $verificationToken, public int $expiresIn) {}
}
