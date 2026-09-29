<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Dto;

use Modules\Organization\Domain\Enums\NetworkStatus;
use Modules\Organization\Domain\Enums\NodeType;

final readonly class NetworkFiltersDto
{
    public function __construct(public string $search = '', public ?NetworkStatus $status = null, public ?string $areaId = null, public ?NodeType $nodeType = null, public int $page = 1, public int $perPage = 20) {}
}
