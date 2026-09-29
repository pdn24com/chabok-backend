<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ListManifestCandidates;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Manifest\Application\Dto\ManifestFiltersDto;

final readonly class ListManifestCandidatesCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public string $id,
        public ManifestFiltersDto $filters,
    ) {}
}
