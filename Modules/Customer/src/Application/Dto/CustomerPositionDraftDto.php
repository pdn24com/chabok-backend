<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Dto;

/** A post inside one department of the chart. */
final readonly class CustomerPositionDraftDto
{
    public function __construct(
        public string $title,
        public ?string $decisionLevel = null,
        /** Descriptive only: it approves nothing and authorises no payment on its own. */
        public ?int $delegationLimit = null,
    ) {}

    public function toAttributes(): array
    {
        return [
            'title' => $this->title,
            'decision_level' => $this->decisionLevel,
            'delegation_limit' => $this->delegationLimit,
        ];
    }
}
