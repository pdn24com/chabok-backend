<?php

declare(strict_types=1);

namespace Modules\Operations\Application\Dto;

use Modules\Operations\Domain\Enums\CoverageTarget;

final readonly class CoveragePolicyFiltersDto
{
    public function __construct(public ?string $search = null, public ?CoverageTarget $target = null, public int $page = 1, public int $perPage = 20) {}

    public static function fromValidated(array $input): self
    {
        return new self($input['search'] ?? null, isset($input['target']) ? CoverageTarget::from($input['target']) : null, (int) ($input['page'] ?? 1), (int) ($input['per_page'] ?? 20));
    }
}
