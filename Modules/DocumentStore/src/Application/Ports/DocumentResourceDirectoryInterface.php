<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\Ports;

use Modules\DocumentStore\Domain\Enums\DocumentResourceType;

/**
 * Port owned by DocumentStore, implemented by the adapter that knows which module answers for each
 * kind of record in the link registry. A link is polymorphic, so nothing but this can tell whether the
 * record on the other end of one exists.
 *
 * @see Modules/DocumentStore/src/Infrastructure/Adapters/DocumentResourceDirectory.php (bound in DocumentStoreServiceProvider)
 */
interface DocumentResourceDirectoryInterface
{
    public function exists(string $hqId, DocumentResourceType $resourceType, string $resourceId): bool;
}
