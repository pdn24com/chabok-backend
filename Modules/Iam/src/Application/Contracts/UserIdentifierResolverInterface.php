<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Contracts;

use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

interface UserIdentifierResolverInterface
{
    /** Resolves a sign-in identifier through every normalized form a User may be reachable by. */
    public function findByIdentifier(string $identifier): ?UserRecord;

    /** @param list<?string> $normalizedIdentifiers */
    public function identifiersExist(array $normalizedIdentifiers): bool;
}
