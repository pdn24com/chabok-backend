<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ConfirmManifest;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ConfirmManifestCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public string $id,
        public int $expected,
        public string $correlationId,
        public ?string $reasonCode = null,
        public ?string $description = null,
    )
    {
    }
}
