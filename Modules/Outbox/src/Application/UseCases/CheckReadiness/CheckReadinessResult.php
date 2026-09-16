<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\UseCases\CheckReadiness;

final readonly class CheckReadinessResult
{
    public function __construct(public array $data)
    {
    }
}
