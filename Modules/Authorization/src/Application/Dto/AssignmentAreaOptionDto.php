<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\Dto;

use Modules\Organization\Infrastructure\Persistence\Models\AreaRecord;

final readonly class AssignmentAreaOptionDto
{
    public function __construct(public AreaRecord $area, public string $path, public bool $canIncludeDescendants) {}
}
