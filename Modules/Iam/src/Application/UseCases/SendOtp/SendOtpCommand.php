<?php

declare(strict_types=1);

namespace Modules\Iam\Application\UseCases\SendOtp;

use Modules\Iam\Domain\Enums\OtpPurpose;

final readonly class SendOtpCommand
{
    public function __construct(
        public string $identifier,
        public OtpPurpose $purpose,
        public string $correlationId,
        public ?string $ip,
    ) {}
}
