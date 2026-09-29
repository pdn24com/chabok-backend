<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\SendOtp;

final readonly class SendOtpResult
{
    public function __construct(public string $challengeId, public int $expiresIn) {}
}
