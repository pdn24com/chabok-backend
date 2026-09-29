<?php

declare(strict_types=1);

namespace Modules\Outbox\Application\Contracts;

use Modules\Foundation\Application\Dto\PublicationReceiptDto;
use Modules\Outbox\Infrastructure\Persistence\Models\OutboxEventRecord;

interface PublicationRecorderInterface
{
    public function complete(
        string $eventId,
        string $claimToken,
        PublicationReceiptDto $receipt,
    ): void;

    public function fail(
        OutboxEventRecord $event,
        string $failureCode,
        bool $retryable,
    ): bool;
}
