<?php

declare(strict_types=1);

namespace Modules\DocumentStore\Application\Repositories;

use Modules\DocumentStore\Domain\Enums\DocumentResourceType;
use Modules\DocumentStore\Infrastructure\Persistence\Models\DocumentLinkRecord;

interface DocumentLinkRepositoryInterface
{
    /** @param array<string, mixed> $attributes */
    public function create(array $attributes): DocumentLinkRecord;

    /** True when this document is already attached to the same record, which it is only ever once. */
    public function exists(string $hqId, string $documentId, DocumentResourceType $resourceType, string $resourceId): bool;
}
