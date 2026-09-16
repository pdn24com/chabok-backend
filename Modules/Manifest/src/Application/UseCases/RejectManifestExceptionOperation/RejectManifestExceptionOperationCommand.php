<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\RejectManifestExceptionOperation;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class RejectManifestExceptionOperationCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $node,
        public string $id,
        public int $manifestVersion,
        public int $exceptionVersion,
        public string $reason,
        public string $correlationId,
    )
    {
    }
}
