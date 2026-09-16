<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\ListOperationalStatusCodes;

final readonly class ListOperationalStatusCodesCommand
{
    public function __construct(public ?string $hqId, public bool $manifestOnly = false)
    {
    }
}
