<?php

declare(strict_types=1);

namespace Modules\Foundation\Application\Ports;

/**
 * Serializes commands for one actor before a new idempotency row exists.
 *
 * Port owned by Foundation, implemented by the Iam module.
 *
 * @see Modules/Iam/src/Infrastructure/Adapters/UserCommandActorLock.php (bound in IamServiceProvider)
 */
interface CommandActorLockInterface
{
    public function lock(string $actorId): void;
}
