<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\SendOtp;

final readonly class SendOtpCommand
{
    public function __construct(public string $identifier, public string $purpose, public string $correlationId, public ?string $ip)
    {
    }
}
