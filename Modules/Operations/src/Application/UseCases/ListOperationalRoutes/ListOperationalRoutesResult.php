<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListOperationalRoutes;

final readonly class ListOperationalRoutesResult
{
    public function __construct(public array $data)
    {
    }
}
