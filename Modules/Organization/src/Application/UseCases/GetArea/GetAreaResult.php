<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\GetArea;

final readonly class GetAreaResult
{
    public function __construct(public array $data)
    {
    }
}
