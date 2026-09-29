<?php

declare(strict_types=1);

namespace Modules\Manifest\Application\Repositories;

use DateTimeInterface;
use Modules\Manifest\Infrastructure\Persistence\Models\ManifestNumberSequenceRecord;

interface ManifestNumberRepositoryInterface
{
    /** Creates the sequence row if this is its first use, then locks it for the caller's transaction. */
    public function lockSequence(string $sequenceKey, DateTimeInterface $at): ?ManifestNumberSequenceRecord;
}
