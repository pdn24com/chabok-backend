<?php

declare(strict_types=1);

namespace Modules\Geography\Application\Contracts;

use Modules\Foundation\Application\Contracts\CanonicalGeographyResolverInterface;

interface GeographyResolverInterface extends CanonicalGeographyResolverInterface
{
    /**
     * Canonical identity always wins over client-supplied province/city snapshots.
     *
     * @param  array<string, mixed>  $contact
     * @return array<string, mixed>
     */
    public function canonicalizeContact(array $contact, bool $required): array;

    /** @return array<string, mixed>|null */
    public function cityReference(?string $cityId): ?array;
}
