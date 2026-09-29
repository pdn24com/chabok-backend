<?php

declare(strict_types=1);

namespace Modules\Organization\Application\Dto;

use Modules\Organization\Domain\Enums\NetworkStatus;

final readonly class AreaChangesDto
{
    public function __construct(public int $expectedVersion, public ?string $title, public ?NetworkStatus $status, public ?string $parentId, public bool $parentSpecified) {}
}
