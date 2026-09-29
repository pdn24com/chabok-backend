<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Contracts;

interface SessionSettingsInterface
{
    public function refreshTtlSeconds(): int;
}
