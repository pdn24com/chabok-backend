<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Carbon\CarbonImmutable;
use Modules\Foundation\Application\Contracts\ClockInterface;
use Modules\Foundation\Domain\Enums\ApiErrorCode;
use Modules\Foundation\Domain\Exceptions\ApiException;
use Modules\Operations\Application\Contracts\RouteVersionGuardInterface;
use Modules\Operations\Application\Dto\RouteVersionDto;
use Modules\Operations\Application\Serialization\RouteDefinitionDocument;
use Modules\Operations\Domain\Enums\ConfigVersionStatus;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionVersionRecord;
use Modules\Organization\Application\Repositories\NodeRepositoryInterface;
use Modules\ServiceCatalog\Application\Repositories\CatalogRepositoryInterface;
use Modules\ServiceCatalog\Domain\Enums\CatalogResource;

final readonly class RouteVersionGuard implements RouteVersionGuardInterface
{
    public function __construct(
        private ClockInterface $clock,
        private NodeRepositoryInterface $nodeRepository,
        private CatalogRepositoryInterface $catalogRepository,
    ) {}

    public function validateContent(
        string $hq,
        RouteVersionDto $content,
    ): void {
        $legs = $content->legs;
        if ($legs === []) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.route_version_is_incomplete');
        }
        if ((int) $content->priority < -100000 || (int) $content->priority > 100000) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.route_priority_is_outside_supported_range');
        }
        if ($content->offeringVersionId !== null && $this->catalogRepository->visibleVersionIdsAmong(CatalogResource::Offering, [$content->offeringVersionId], $hq) === []) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.offering_version_is_not_visible_hq');
        }
        $nodeIds = [];
        foreach ($legs as $leg) {
            $nodeIds[] = $leg->originNodeId;
            $nodeIds[] = $leg->destinationNodeId;
        }
        $activeNodes = collect($this->nodeRepository->activeIdsAmong($hq, $nodeIds))->flip();
        $seen = [];
        $previous = null;
        foreach (array_values($legs) as $index => $leg) {
            if ((int) $leg->order !== $index + 1) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.route_leg_order_values_must_be_contiguous');
            }
            if ($previous !== null && $leg->originNodeId !== $previous) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.route_leg_chain_is_broken');
            }
            foreach ([$leg->originNodeId, $leg->destinationNodeId] as $nodeId) {
                if (! $activeNodes->has($nodeId)) {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.every_route_node_must_be_active_belong');
                }
            }
            if ($leg->originNodeId === $leg->destinationNodeId || isset($seen[$leg->destinationNodeId])) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.route_templates_cannot_contain_cycles');
            }
            $seen[$leg->originNodeId] = true;
            $previous = $leg->destinationNodeId;
        }
        if ($legs[0]->originNodeId !== $content->originNodeId || $legs[count($legs) - 1]->destinationNodeId !== $content->destinationNodeId) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.route_endpoints_must_match_first_last_leg');
        }
        if ($content->effectiveFrom !== null && $content->effectiveTo !== null && CarbonImmutable::parse($content->effectiveFrom)->gte(CarbonImmutable::parse($content->effectiveTo))) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.effective_must_be_after_effective_from');
        }
    }

    public function expected(RouteDefinitionVersionRecord $row, int $expected): void
    {
        if ((int) $row->version !== $expected) {
            throw new ApiException(ApiErrorCode::VersionConflict, 409, 'operations.route_version_is_stale', details: ['current_version' => (int) $row->version]);
        }
    }

    public function validatedChanges(
        string $hq,
        RouteDefinitionVersionRecord $row,
        string $user,
    ): array {
        if ($row->status !== ConfigVersionStatus::Draft->value) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.only_draft_can_be_validated');
        }
        $this->validateContent($hq, RouteVersionDto::fromRecord($row));

        return [
            'status' => ConfigVersionStatus::Validated->value,
            'validated_by' => $user,
            'validated_at' => $this->clock->now(),
        ];
    }

    public function simpleChanges(
        RouteDefinitionVersionRecord $row,
        string $from,
        string $to,
        array $extra = [],
    ): array {
        if ($row->status !== $from) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.only_can_transition', messageParams: ['from' => $from, 'to' => $to]);
        }

        return ['status' => $to, ...$extra];
    }

    public function archiveChanges(RouteDefinitionVersionRecord $row): array
    {
        if (! in_array($row->status, [ConfigVersionStatus::Draft->value, ConfigVersionStatus::Validated->value, ConfigVersionStatus::Approved->value], true)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.published_superseded_versions_cannot_be_archived');
        }

        return ['status' => ConfigVersionStatus::Archived->value];
    }

    public function publishedChanges(
        string $hq,
        RouteDefinitionVersionRecord $row,
        string $user,
    ): array {
        if ($row->status !== ConfigVersionStatus::Approved->value) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'operations.only_approved_version_can_be_published');
        }
        $this->validateContent($hq, RouteVersionDto::fromRecord($row));

        return [
            'status' => ConfigVersionStatus::Published->value,
            'published_by' => $user,
            'published_at' => $this->clock->now(),
            'content_digest' => hash('sha256', json_encode(RouteDefinitionDocument::version($row), JSON_THROW_ON_ERROR)),
        ];
    }
}
