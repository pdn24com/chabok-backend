<?php

declare(strict_types=1);

namespace Modules\ServiceCatalog\Application\Repositories;

use Modules\ServiceCatalog\Domain\Enums\OfferingChild;

interface OfferingChildrenRepositoryInterface
{
    /** Clears every child collection of one Offering version before it is rewritten. */
    public function deleteAll(string $versionId): void;

    /** @param list<array<string, mixed>> $rows Attribute sets; the repository applies the model's own casts. */
    public function insert(OfferingChild $child, array $rows): void;

    /** Copies every child collection onto a new version, giving each row a fresh identifier. */
    public function cloneAll(string $from, string $to): void;
}
