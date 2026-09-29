<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Ports;

use Modules\Iam\Application\Dto\UserAssignmentDto;

/**
 * Port owned by Iam, implemented by the Authorization module.
 *
 * @see Modules/Authorization/src/Infrastructure/Adapters/AuthorizationUserAssignmentReader.php (bound in AuthorizationServiceProvider)
 */
interface UserAssignmentReaderInterface
{
    /** @return list<UserAssignmentDto> */
    public function forUser(string $hqId, string $userId): array;
}
