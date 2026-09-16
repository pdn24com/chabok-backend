<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\SendOtp;

final readonly class SendOtpResult
{
    public function __construct(public array $data)
    {
    }
}
