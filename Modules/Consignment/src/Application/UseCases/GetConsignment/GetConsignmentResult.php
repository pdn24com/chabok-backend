<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\GetConsignment;

final readonly class GetConsignmentResult
{
    public function __construct(public array $data)
    {
    }
}
