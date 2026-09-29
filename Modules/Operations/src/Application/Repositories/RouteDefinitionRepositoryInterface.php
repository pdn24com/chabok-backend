<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Repositories;

use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection as SupportCollection;
use Modules\Operations\Application\Dto\RouteDefinitionFiltersDto;
use Modules\Operations\Domain\Enums\RoutePurpose;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionVersionRecord;

interface RouteDefinitionRepositoryInterface
{
    public function definitionExists(?string $hqId, string $definitionId): bool;

    public function codeTaken(?string $hqId, string $code): bool;

    public function findDefinition(?string $hqId, string $definitionId): ?RouteDefinitionRecord;

    /** @return LengthAwarePaginator<RouteDefinitionRecord> */
    public function paginateDefinitions(?string $hqId, RouteDefinitionFiltersDto $filters): LengthAwarePaginator;

    /** Active routes at a tenant with their active legs and end Nodes, for an operational route list. @return Collection<int, RouteDefinitionRecord> */
    public function activeDefinitionsWithLegs(?string $hqId): Collection;

    /** Raises the version counter and publishes this version as the definition's current one. @param array<string, mixed> $changes */
    public function publishVersion(string $definitionId, array $changes): void;

    /** Clears the published pointer only while it still names this version. @param array<string, mixed> $changes */
    public function retirePublishedVersion(string $definitionId, string $versionId, array $changes): void;

    public function findVersionWithLegs(?string $hqId, string $versionId): ?RouteDefinitionVersionRecord;

    public function findDefinitionVersionWithLegs(?string $hqId, string $definitionId, string $versionId): ?RouteDefinitionVersionRecord;

    public function lockDefinitionVersionWithLegs(?string $hqId, string $definitionId, string $versionId): ?RouteDefinitionVersionRecord;

    /** @return LengthAwarePaginator<RouteDefinitionVersionRecord> */
    public function paginateVersions(?string $hqId, string $definitionId, int $page, int $perPage): LengthAwarePaginator;

    public function nextVersionNumber(string $definitionId): int;

    /** Published versions for a lane whose definition still points at them, effective at a moment, best first. @return Collection<int, RouteDefinitionVersionRecord> */
    public function effectiveVersionsForLane(string $hqId, RoutePurpose $purpose, string $originNodeId, string $destinationNodeId, ?string $offeringVersionId, DateTimeInterface $at): Collection;

    /** Replaces the ordered legs one version owns. @param list<array<string, mixed>> $rows */
    public function replaceVersionLegs(string $versionId, array $rows): void;

    /** Replaces the definition-level leg snapshot a plan copies from. @param list<array<string, mixed>> $rows */
    public function replaceDefinitionLegs(string $definitionId, array $rows): void;

    /** Active definition-level legs keyed by order, which is the snapshot a plan copies. @return SupportCollection<int, string> */
    public function activeDefinitionLegsByOrder(?string $hqId, ?string $definitionId): SupportCollection;
}
