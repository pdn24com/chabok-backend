<?php

declare(strict_types=1);

namespace Modules\Foundation\Domain\ValueObjects;

final readonly class AreaEdge
{
    public function __construct(public string $parentAreaId, public string $childAreaId) {}
}
