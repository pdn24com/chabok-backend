<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Contracts;

use Modules\Iam\Domain\Enums\OtpPurpose;

interface VerificationProofConsumerInterface
{
    public function consumeVerificationToken(
        string $raw,
        OtpPurpose $purpose,
        ?string $expectedUserId = null,
    ): string;
}
