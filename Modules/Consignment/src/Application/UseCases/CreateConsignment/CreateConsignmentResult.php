<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\CreateConsignment;

final readonly class CreateConsignmentResult
{
    public function __construct(public array $data)
    {
    }
}
