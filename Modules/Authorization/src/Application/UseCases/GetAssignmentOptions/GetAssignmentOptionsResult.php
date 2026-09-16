<?php

declare(strict_types=1);

namespace Modules\Authorization\Application\UseCases\GetAssignmentOptions;

final readonly class GetAssignmentOptionsResult
{
    public function __construct(public array $data)
    {
    }
}
