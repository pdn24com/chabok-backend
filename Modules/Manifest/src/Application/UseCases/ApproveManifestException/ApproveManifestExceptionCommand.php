<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ApproveManifestException;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ApproveManifestExceptionCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public string $id,
        public int $manifestVersion,
        public int $exceptionVersion,
        public ?string $reason,
        public string $correlationId,
    )
    {
    }
}
