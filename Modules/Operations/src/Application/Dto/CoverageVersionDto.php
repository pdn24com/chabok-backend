<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Dto;

final readonly class CoverageVersionDto
{
    /** @param list<CoverageRuleDto> $rules */
    public function __construct(public ?string $effectiveFrom, public ?string $effectiveTo, public array $rules, public ?string $sourceVersionId = null) {}

    public static function fromValidated(array $input): self
    {
        return new self($input['effective_from'] ?? null, $input['effective_to'] ?? null,
            array_map(CoverageRuleDto::fromValidated(...), $input['rules'] ?? []), $input['source_version_id'] ?? null);
    }
}
