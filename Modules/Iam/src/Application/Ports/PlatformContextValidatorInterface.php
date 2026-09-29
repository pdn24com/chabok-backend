<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Ports;

/**
 * Port owned by Iam, implemented by the Authorization module.
 *
 * @see Modules/Authorization/src/Infrastructure/Adapters/AuthorizationPlatformContextValidator.php (bound in AuthorizationServiceProvider)
 */
interface PlatformContextValidatorInterface
{
    public function hasActivePlatformAssignment(string $userId): bool;
}
