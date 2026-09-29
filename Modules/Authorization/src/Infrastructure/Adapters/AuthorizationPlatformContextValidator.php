<?php

declare(strict_types=1);

namespace Modules\Authorization\Infrastructure\Adapters;

use Modules\Authorization\Application\Contracts\AuthorizationGuardInterface;
use Modules\Iam\Application\Ports\PlatformContextValidatorInterface;

final readonly class AuthorizationPlatformContextValidator implements PlatformContextValidatorInterface
{
    public function __construct(private AuthorizationGuardInterface $authorizationGuard) {}

    public function hasActivePlatformAssignment(string $userId): bool
    {
        return $this->authorizationGuard->hasActivePlatformAssignment($userId);
    }
}
