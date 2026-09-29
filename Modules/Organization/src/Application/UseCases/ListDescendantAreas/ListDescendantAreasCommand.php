<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\ListDescendantAreas;

final readonly class ListDescendantAreasCommand
{
    public function __construct(public string $hqId, public string $areaId) {}
}
