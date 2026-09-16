<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\CreateArea;

final readonly class CreateAreaResult
{
    public function __construct(public array $data)
    {
    }
}
