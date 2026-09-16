<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\SaveOperationalStatus;

final readonly class SaveOperationalStatusResult
{
    public function __construct(public array $data)
    {
    }
}
