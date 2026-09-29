<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Dto;

final readonly class ManifestFiltersDto
{
    /** @param list<string> $states @param list<string> $statuses */
    public function __construct(
        public ?string $search = null,
        public array $states = [],
        public array $statuses = [],
        public int $pageSize = 25,
        public int $page = 1,
    ) {}

    public static function fromArray(array $input): self
    {
        return new self($input['search'] ?? null, (array) ($input['state'] ?? []), (array) ($input['manifest_status'] ?? []), (int) ($input['page_size'] ?? 25), (int) ($input['page'] ?? 1));
    }
}
