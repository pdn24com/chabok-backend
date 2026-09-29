<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Dto;

final readonly class ManifestRouteSelectionDto
{
    public function __construct(public ManifestRouteReferenceDto $evidence, public ?string $activePlanId, public ?string $activeLegId) {}
}
