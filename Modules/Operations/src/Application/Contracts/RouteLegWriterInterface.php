<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Modules\Operations\Application\Dto\RouteLegDto;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionVersionRecord;

interface RouteLegWriterInterface
{
    /** @param list<RouteLegDto> $legs */
    public function replaceLegs(string $hq, string $versionId, array $legs): void;

    public function syncLegacyLegs(RouteDefinitionVersionRecord $version): void;
}
