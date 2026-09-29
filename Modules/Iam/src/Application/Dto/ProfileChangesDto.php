<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Dto;

/** Null means omitted; the HTTP contract accepts only non-null strings. */
final readonly class ProfileChangesDto
{
    public function __construct(public ?string $firstName = null, public ?string $lastName = null, public ?string $displayName = null) {}

    /** Columns supplied by the caller; empty strings remain intentional edits. */
    public function attributes(): array
    {
        return array_filter(['first_name' => $this->firstName, 'last_name' => $this->lastName, 'display_name' => $this->displayName],
            static fn (?string $value): bool => $value !== null);
    }
}
