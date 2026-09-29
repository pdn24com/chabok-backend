<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Dto;

final readonly class ManifestRouteReferenceDto
{
    public function __construct(
        public ?string $planId,
        public ?string $definitionVersionId,
        public ?string $legId,
        public ?string $definitionVersionLegId,
    ) {}
}
