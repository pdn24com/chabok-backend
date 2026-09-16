<?php

declare(strict_types=1);

namespace Modules\Operations\Application\UseCases\ListAvailableDrivers;

final readonly class ListAvailableDriversResult
{
    public function __construct(public array $data)
    {
    }
}
