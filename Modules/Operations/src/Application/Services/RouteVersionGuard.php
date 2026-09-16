<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Services;

use Carbon\CarbonImmutable as Carbon;
use Modules\Foundation\Domain\ApiErrorCode;
use Modules\Foundation\Domain\ApiException;

final readonly class RouteVersionGuard
{
    public function __construct(
        private \Modules\Operations\Application\Repositories\RouteDefinitionRepository $routes,
        private \Modules\Operations\Application\Services\RouteDefinitionReader $routeDefinitionReader,
        private \Modules\Foundation\Application\Contracts\Clock $clock,
    )
    {
    }

    public function validateContent(string $hq, array $content, array $legs): void
    {
        if (!in_array($content['purpose'] ?? null, ['TRUNK', 'LAST_MILE'], true) || !isset($content['origin_node_id'], $content['destination_node_id'], $content['priority']) || $legs === []) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Route Version is incomplete.');
        }
        if ((int) $content['priority'] < -100000 || (int) $content['priority'] > 100000) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Route priority is outside the supported range.');
        }
        if (($content['offering_version_id'] ?? null) !== null && !$this->routes->offeringVisible($hq, $content['offering_version_id'])) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Offering Version is not visible to this HQ.');
        }
        $seen = [];
        $previous = null;
        foreach (array_values($legs) as $index => $leg) {
            if ((int) ($leg['leg_order'] ?? 0) !== $index + 1) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Route leg_order values must be contiguous and start at one.');
            }
            if ($previous !== null && ($leg['origin_node_id'] ?? null) !== $previous) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'The Route leg chain is broken.');
            }
            foreach (['origin_node_id', 'destination_node_id'] as $field) {
                if (!$this->routes->activeNode($hq, $leg[$field] ?? '')) {
                    throw new ApiException(ApiErrorCode::ValidationError, 422, 'Every Route Node must be active and belong to the current HQ.');
                }
            }
            if ($leg['origin_node_id'] === $leg['destination_node_id'] || isset($seen[$leg['destination_node_id']])) {
                throw new ApiException(ApiErrorCode::ValidationError, 422, 'Route templates cannot contain cycles.');
            }
            $seen[$leg['origin_node_id']] = true;
            $previous = $leg['destination_node_id'];
        }
        if ($legs[0]['origin_node_id'] !== $content['origin_node_id'] || $legs[count($legs) - 1]['destination_node_id'] !== $content['destination_node_id']) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Route endpoints must match the first and last Leg.');
        }
        if (($content['effective_from'] ?? null) !== null && ($content['effective_to'] ?? null) !== null && Carbon::parse($content['effective_from'])->gte(Carbon::parse($content['effective_to']))) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'effective_to must be after effective_from.');
        }
    }

    public function expected(object $row, int $expected): void
    {
        if ((int) $row->version !== $expected) {
            throw new ApiException(ApiErrorCode::VersionConflict, 409, 'The Route Version is stale.', details: ['current_version' => (int) $row->version]);
        }
    }

    public function validatedChanges(string $hq, object $row, string $user): array
    {
        if ($row->status !== 'DRAFT') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only a draft can be validated.');
        }
        $this->validateContent($hq, (array) $row, $this->routeDefinitionReader->legInputs($hq, $row->route_definition_version_id));
        return ['status' => 'VALIDATED', 'validated_by' => $user, 'validated_at' => $this->clock->now()];
    }

    public function simpleChanges(object $row, string $from, string $to, array $extra = []): array
    {
        if ($row->status !== $from) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, "Only {$from} can transition to {$to}.");
        }
        return ['status' => $to, ...$extra];
    }

    public function archiveChanges(object $row): array
    {
        if (!in_array($row->status, ['DRAFT', 'VALIDATED', 'APPROVED'], true)) {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Published or superseded versions cannot be archived.');
        }
        return ['status' => 'ARCHIVED'];
    }

    public function publishedChanges(string $hq, object $row, string $user): array
    {
        if ($row->status !== 'APPROVED') {
            throw new ApiException(ApiErrorCode::ValidationError, 422, 'Only an approved version can be published.');
        }
        $this->validateContent($hq, (array) $row, $this->routeDefinitionReader->legInputs($hq, $row->route_definition_version_id));
        return [
            'status' => 'PUBLISHED',
            'published_by' => $user,
            'published_at' => $this->clock->now(),
            'content_digest' => hash('sha256', json_encode($this->routeDefinitionReader->versionArray($row), JSON_THROW_ON_ERROR)),
        ];
    }
}
