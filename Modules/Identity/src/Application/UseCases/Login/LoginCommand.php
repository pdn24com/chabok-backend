<?php

declare(strict_types=1);

namespace Modules\Identity\Application\UseCases\Login;

final readonly class LoginCommand
{
    public function __construct(
        public string $identifier,
        public string $password,
        public ?string $deviceId,
        public ?string $deviceName,
        public string $correlationId,
        public ?string $ip,
        public ?string $userAgent,
    )
    {
    }
}
