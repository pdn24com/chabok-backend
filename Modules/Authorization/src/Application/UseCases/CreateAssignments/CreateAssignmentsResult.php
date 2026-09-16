<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\CreateAssignments;

final readonly class CreateAssignmentsResult
{
    public function __construct(public array $data)
    {
    }
}
