<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Adapters;

use Modules\Authorization\Application\AuthorizationService;
use Modules\Identity\Application\Contracts\PlatformContextValidator;

final readonly class AuthorizationPlatformContextValidator implements PlatformContextValidator
{
    public function __construct(private AuthorizationService $authorization) {}

    public function hasActivePlatformAssignment(string $userId): bool
    {
        return $this->authorization->hasActivePlatformAssignment($userId);
    }
}
