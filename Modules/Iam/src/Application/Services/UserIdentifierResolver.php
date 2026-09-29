<?php

declare(strict_types=1);

namespace Modules\Iam\Application\Services;

use Modules\Iam\Application\Contracts\IdentifierNormalizerInterface;
use Modules\Iam\Application\Contracts\UserIdentifierResolverInterface;
use Modules\Iam\Application\Repositories\UserRepositoryInterface;
use Modules\Iam\Infrastructure\Persistence\Models\UserRecord;

final readonly class UserIdentifierResolver implements UserIdentifierResolverInterface
{
    public function __construct(
        private IdentifierNormalizerInterface $identifierNormalizer,
        private UserRepositoryInterface $userRepository,
    ) {}

    public function findByIdentifier(string $identifier): ?UserRecord
    {
        $values = array_values(array_unique(array_filter([
            $this->identifierNormalizer->username($identifier), $this->identifierNormalizer->mobile($identifier), $this->identifierNormalizer->email($identifier),
        ], static fn (?string $value): bool => $value !== null && $value !== '')));
        if ($values === []) {
            return null;
        }

        return $this->userRepository->findByNormalizedIdentifiers($values);
    }

    /** @param list<?string> $normalizedIdentifiers */
    public function identifiersExist(array $normalizedIdentifiers): bool
    {
        $values = array_values(array_unique(array_filter($normalizedIdentifiers, static fn (?string $value): bool => $value !== null && $value !== '')));
        if ($values === []) {
            return false;
        }

        return $this->userRepository->normalizedIdentifiersExist($values);
    }
}
