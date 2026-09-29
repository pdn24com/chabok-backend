<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Dto;

final readonly class CoverageVersionChangesDto
{
    /** @param list<CoverageRuleDto>|null $rules */
    public function __construct(public int $expectedVersion, public ?string $effectiveFrom, public bool $effectiveFromProvided,
        public ?string $effectiveTo, public bool $effectiveToProvided, public ?array $rules) {}

    public static function fromValidated(array $input): self
    {
        return new self((int) $input['expected_version'], $input['effective_from'] ?? null, array_key_exists('effective_from', $input),
            $input['effective_to'] ?? null, array_key_exists('effective_to', $input),
            array_key_exists('rules', $input) ? array_map(CoverageRuleDto::fromValidated(...), $input['rules']) : null);
    }
}
