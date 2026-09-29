<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Dto;

final readonly class RouteVersionChangesDto
{
    /** @param list<RouteLegDto>|null $legs */
    public function __construct(
        public int $expectedVersion,
        public ?int $priority,
        public ?string $offeringVersionId,
        public bool $offeringVersionProvided,
        public ?string $effectiveFrom,
        public bool $effectiveFromProvided,
        public ?string $effectiveTo,
        public bool $effectiveToProvided,
        public ?array $legs,
    ) {}

    public static function fromValidated(array $input): self
    {
        return new self(
            (int) $input['expected_version'], isset($input['priority']) ? (int) $input['priority'] : null,
            $input['offering_version_id'] ?? null, array_key_exists('offering_version_id', $input),
            $input['effective_from'] ?? null, array_key_exists('effective_from', $input),
            $input['effective_to'] ?? null, array_key_exists('effective_to', $input),
            array_key_exists('legs', $input) ? array_map(RouteLegDto::fromValidated(...), $input['legs']) : null,
        );
    }

    public function applyTo(RouteVersionDto $current): RouteVersionDto
    {
        return new RouteVersionDto($current->purpose, $current->originNodeId, $current->destinationNodeId,
            $this->priority ?? $current->priority,
            $this->offeringVersionProvided ? $this->offeringVersionId : $current->offeringVersionId,
            $this->effectiveFromProvided ? $this->effectiveFrom : $current->effectiveFrom,
            $this->effectiveToProvided ? $this->effectiveTo : $current->effectiveTo,
            $this->legs ?? $current->legs);
    }
}
