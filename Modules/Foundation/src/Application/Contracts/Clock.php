<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Contracts;

interface Clock
{
    public function now(): \DateTimeImmutable;
}
