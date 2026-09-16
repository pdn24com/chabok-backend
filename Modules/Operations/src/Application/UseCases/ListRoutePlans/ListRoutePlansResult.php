<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListRoutePlans;

final readonly class ListRoutePlansResult
{
    public function __construct(public array $data)
    {
    }
}
