<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\UseCases\ExecuteIdempotentCommand;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ExecuteIdempotentCommandCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $name,
        public string $key,
        public string $fingerprint,
    )
    {
    }
}
