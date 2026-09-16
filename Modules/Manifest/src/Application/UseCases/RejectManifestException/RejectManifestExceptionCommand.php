<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\RejectManifestException;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class RejectManifestExceptionCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public string $id,
        public int $manifestVersion,
        public int $exceptionVersion,
        public string $reason,
        public string $correlationId,
    )
    {
    }
}
