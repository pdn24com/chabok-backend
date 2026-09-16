<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ListOperationalStatusCodes;

final readonly class ListOperationalStatusCodesResult
{
    public function __construct(public array $data)
    {
    }
}
