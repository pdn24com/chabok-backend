<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ListManifestCandidates;

use Modules\Foundation\Domain\AuthenticatedPrincipal;

final readonly class ListManifestCandidatesCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public string $id,
        public array $filters,
    )
    {
    }
}
