<?php

declare(strict_types=1);

namespace Modules\Identity\Infrastructure\Authorization;

use Modules\Identity\Application\Contracts\PlatformContextValidator;

final class UnavailablePlatformContextValidator implements PlatformContextValidator
{
    public function hasActivePlatformAssignment(string $userId): bool
    {
        return false;
    }
}
