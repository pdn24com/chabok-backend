<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\UpdateAssignment;

final readonly class UpdateAssignmentResult
{
    public function __construct(public array $data)
    {
    }
}
