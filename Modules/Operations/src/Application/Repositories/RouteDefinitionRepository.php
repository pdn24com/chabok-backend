<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Repositories;

use Modules\Foundation\Application\Data\Page;

interface RouteDefinitionRepository
{
    public function definitions(string $hq, array $filters): Page;

    public function codeExists(string $hq, string $code): bool;

    public function definition(string $hq, string $id): ?object;

    public function definitionExists(string $hq, string $definitionId): bool;

    public function history(string $hq, string $definitionId, int $page, int $perPage): Page;

    public function lastVersionNumber(string $definitionId): int;

    public function insertDefinition(array $attributes): void;

    public function insertVersion(array $attributes): void;

    public function insertVersionLeg(array $attributes): void;

    public function insertLegacyLeg(array $attributes): void;

    public function deleteVersionLegs(string $id): void;

    public function deleteLegacyLegs(string $id): void;

    public function updateVersion(string $versionId, array $changes): void;

    public function publishDefinition(string $definitionId, string $versionId, \DateTimeInterface $at): void;

    public function supersedeDefinition(string $definitionId, string $versionId, \DateTimeInterface $at): void;

    public function matchingVersions(
        string $hqId,
        string $purpose,
        string $originNodeId,
        string $destinationNodeId,
        ?string $offeringVersionId,
        \DateTimeInterface $at,
    ): array;

    public function offeringVisible(string $hq, string $offeringVersionId): bool;

    public function activeNode(string $hq, string $nodeId): bool;

    public function versionExists(string $hq, string $versionId): bool;

    public function version(string $hq, string $definition, string $version): ?object;

    public function lockVersion(string $hq, string $definition, string $version): ?object;

    public function versionLegs(string $versionId): array;
}
