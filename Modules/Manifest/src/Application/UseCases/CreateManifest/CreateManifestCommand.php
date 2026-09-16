<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\CreateManifest;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class CreateManifestCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public array $input,
        public string $correlationId,
    )
    {
    }
}
