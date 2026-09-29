<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\VerifyOtp;

final readonly class VerifyOtpCommand
{
    public function __construct(public string $challengeId, public string $code) {}
}
