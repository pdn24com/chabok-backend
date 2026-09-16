<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ConfirmManifestOperation;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ConfirmManifestOperationCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $node,
        public string $id,
        public int $expected,
        public string $correlationId,
        public ?string $reasonCode = null,
        public ?string $description = null,
    )
    {
    }
}
