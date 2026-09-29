<?php

declare(strict_types=1);

namespace Modules\Customer\Application\Repositories;

use Illuminate\Database\Eloquent\Collection;
use Modules\Customer\Infrastructure\Persistence\Models\ContactPointRecord;

interface ContactPointRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): ContactPointRecord;

    /**
     * Every contact point of one person, default entries first, then by priority (unset last) and ID. A
     * person owns a handful of channels, so the whole set is read at once rather than a page of it.
     *
     * @return Collection<int, ContactPointRecord>
     */
    public function listForCustomer(string $hqId, string $customerId): Collection;

    /**
     * The same set as {@see listForCustomer()}, read for update; the caller must already be inside a transaction.
     *
     * @return Collection<int, ContactPointRecord>
     */
    public function lockForCustomer(string $hqId, string $customerId): Collection;

    /** @param array<string, mixed> $attributes */
    public function update(string $hqId, string $contactPointId, array $attributes): void;

    /** @param list<string> $contactPointIds */
    public function deleteForCustomer(string $hqId, string $customerId, array $contactPointIds): void;

    /**
     * The ACTIVE contact points of the tenant holding one normalised value on one channel type, newest
     * first. With `$exceptCustomerId` the person being edited is left out; with `$lock` the rows are read
     * for update (and, under MySQL's default isolation, the index range around them is gap-locked), so the
     * caller must already be inside a transaction.
     *
     * @return Collection<int, ContactPointRecord>
     */
    public function findActiveOwnersOfNormalizedValue(
        string $hqId,
        string $type,
        string $normalizedValue,
        ?string $exceptCustomerId = null,
        bool $lock = false,
        int $limit = 100,
    ): Collection;
}
