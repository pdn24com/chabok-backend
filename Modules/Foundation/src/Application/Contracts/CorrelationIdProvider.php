<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Contracts;

interface CorrelationIdProvider
{
    public function current(): string;
}
