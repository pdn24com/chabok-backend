<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Dto;

final readonly class CommitmentScheduleFiltersDto
{
    public function __construct(public string $search = '', public int $page = 1, public int $pageSize = 25) {}

    public static function fromValidated(array $input): self
    {
        return new self((string) ($input['search'] ?? ''), max(1, (int) ($input['page'] ?? 1)), min(100, max(1, (int) ($input['page_size'] ?? 25))));
    }
}
