<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ValidateManifest;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ValidateManifestCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public string $id,
        public int $expected,
        public string $correlationId,
    )
    {
    }
}
