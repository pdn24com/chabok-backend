<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\UpdateManifest;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class UpdateManifestCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public string $id,
        public array $input,
        public string $correlationId,
    )
    {
    }
}
