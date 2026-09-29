<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Contracts;

interface CorrelationIdProviderInterface
{
    public function current(): string;
}
