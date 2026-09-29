<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Dto;

final readonly class CustomerPositionChangesDto
{
    public function __construct(
        public ?string $title = null,
        public ?string $decisionLevel = null,
        public bool $decisionLevelSpecified = false,
        public ?int $delegationLimit = null,
        public bool $delegationLimitSpecified = false,
    ) {}

    public function touchesNothing(): bool
    {
        return $this->title === null && ! $this->decisionLevelSpecified && ! $this->delegationLimitSpecified;
    }

    public function toAttributes(): array
    {
        $attributes = [];
        if ($this->title !== null) {
            $attributes['title'] = $this->title;
        }
        if ($this->decisionLevelSpecified) {
            $attributes['decision_level'] = $this->decisionLevel;
        }
        if ($this->delegationLimitSpecified) {
            $attributes['delegation_limit'] = $this->delegationLimit;
        }

        return $attributes;
    }
}
