<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\ListDescendantAreas;

final readonly class ListDescendantAreasResult
{
    public function __construct(public array $data)
    {
    }
}
