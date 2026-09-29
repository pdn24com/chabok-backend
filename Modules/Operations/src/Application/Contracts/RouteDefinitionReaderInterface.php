<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Contracts;

use Modules\Operations\Application\Dto\RouteLegDto;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionVersionRecord;

interface RouteDefinitionReaderInterface
{
    /** @return list<RouteLegDto> */
    public function legInputs(string $hq, string $versionId): array;

    public function versionRow(string $hq, string $definition, string $version): RouteDefinitionVersionRecord;

    public function lockedVersion(string $hq, string $definition, string $version): RouteDefinitionVersionRecord;
}
