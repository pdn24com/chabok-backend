<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\VerifyOtp;

final readonly class VerifyOtpResult
{
    public function __construct(public array $data)
    {
    }
}
