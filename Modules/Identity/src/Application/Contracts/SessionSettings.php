<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Contracts;

interface SessionSettings
{
    public function refreshTtlSeconds(): int;
}
