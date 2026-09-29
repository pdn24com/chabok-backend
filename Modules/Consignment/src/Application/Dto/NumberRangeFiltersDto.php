<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\Dto;

use Modules\Consignment\Domain\Enums\NumberRangeStatus;

final readonly class NumberRangeFiltersDto
{
    public function __construct(public ?NumberRangeStatus $status = null, public int $pageSize = 25, public int $page = 1) {}

    public static function fromValidated(array $input): self
    {
        return new self(isset($input['status']) ? NumberRangeStatus::from($input['status']) : null, (int) ($input['page_size'] ?? 25), (int) ($input['page'] ?? 1));
    }
}
