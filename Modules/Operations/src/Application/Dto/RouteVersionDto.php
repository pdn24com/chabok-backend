<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Dto;

use Modules\Operations\Domain\Enums\RoutePurpose;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionVersionLegRecord;
use Modules\Operations\Infrastructure\Persistence\Models\RouteDefinitionVersionRecord;

final readonly class RouteVersionDto
{
    /** @param list<RouteLegDto> $legs */
    public function __construct(
        public RoutePurpose $purpose,
        public string $originNodeId,
        public string $destinationNodeId,
        public int $priority,
        public ?string $offeringVersionId,
        public ?string $effectiveFrom,
        public ?string $effectiveTo,
        public array $legs,
        public ?string $sourceVersionId = null,
    ) {}

    public static function fromValidated(array $input): self
    {
        return new self(
            RoutePurpose::from($input['purpose']), $input['origin_node_id'], $input['destination_node_id'], (int) $input['priority'],
            $input['offering_version_id'] ?? null, $input['effective_from'] ?? null, $input['effective_to'] ?? null,
            array_map(RouteLegDto::fromValidated(...), $input['legs'] ?? []), $input['source_version_id'] ?? null,
        );
    }

    public static function fromRecord(RouteDefinitionVersionRecord $version): self
    {
        return new self(
            RoutePurpose::from($version->purpose), $version->origin_node_id, $version->destination_node_id, $version->priority,
            $version->offering_version_id, $version->effective_from, $version->effective_to,
            $version->legs->map(fn (RouteDefinitionVersionLegRecord $leg): RouteLegDto => new RouteLegDto($leg->leg_order, $leg->origin_node_id, $leg->destination_node_id))->all(),
        );
    }

    /** @param list<RouteLegDto> $legs */
    public function withLegs(array $legs): self
    {
        return new self($this->purpose, $this->originNodeId, $this->destinationNodeId, $this->priority,
            $this->offeringVersionId, $this->effectiveFrom, $this->effectiveTo, $legs, $this->sourceVersionId);
    }
}
