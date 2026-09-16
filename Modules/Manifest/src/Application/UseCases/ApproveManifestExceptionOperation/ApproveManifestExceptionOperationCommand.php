<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ApproveManifestExceptionOperation;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ApproveManifestExceptionOperationCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $node,
        public string $id,
        public int $manifestVersion,
        public int $exceptionVersion,
        public ?string $reason,
        public string $correlationId,
    )
    {
    }
}
