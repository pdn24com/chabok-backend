<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\UseCases\ListManifests;

use Modules\Foundation\Domain\ValueObjects\AuthenticatedPrincipal;
use Modules\Manifest\Application\Dto\ManifestFiltersDto;

final readonly class ListManifestsCommand
{
    public function __construct(
        public AuthenticatedPrincipal $actor,
        public string $nodeId,
        public ManifestFiltersDto $filters,
    ) {}
}
