<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\AddAreaEdge;

final readonly class AddAreaEdgeCommand
{
    public function __construct(public string $hqId, public string $parentAreaId, public string $childAreaId)
    {
    }
}
