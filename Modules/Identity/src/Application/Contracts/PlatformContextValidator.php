<?php

declare(strict_types=1);

namespace Modules\Identity\Application\Contracts;

interface PlatformContextValidator
{
    public function hasActivePlatformAssignment(string $userId): bool;
}
