<?php

declare(strict_types=1);

namespace Modules\Organization\Application\UseCases\UpdateArea;

final readonly class UpdateAreaResult
{
    public function __construct(public array $data)
    {
    }
}
