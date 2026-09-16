<?php

declare(strict_types=1);

namespace Modules\User\Application\UseCases\AttachOperationalProfile;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class AttachOperationalProfileCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $userId,
        public array $input,
        public string $correlationId,
    )
    {
    }
}
