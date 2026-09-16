<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ResubmitManifestExceptionOperation;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ResubmitManifestExceptionOperationCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $node,
        public string $id,
        public int $manifestVersion,
        public int $exceptionVersion,
        public string $code,
        public string $description,
        public string $correlationId,
    )
    {
    }
}
