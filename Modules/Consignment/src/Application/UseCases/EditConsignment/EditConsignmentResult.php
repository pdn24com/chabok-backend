<?php

declare(strict_types=1);

namespace Modules\Consignment\Application\UseCases\EditConsignment;

final readonly class EditConsignmentResult
{
    public function __construct(public array $data)
    {
    }
}
